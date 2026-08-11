/**
 * In-process sliding-window rate limiter.
 *
 * This app runs as a single `next-server` standalone process on the Novatrend
 * box (see CLAUDE.md: "exactly one next-server should run", enforced by the
 * health-check watchdog / deploy checks). That makes a plain in-memory Map a
 * legitimate limiter for this deployment, but it comes with two honest
 * caveats callers must accept:
 *
 *  - State resets on every process restart (deploy, crash, watchdog restart).
 *    A restart gives every client a fresh window; this is a minor availability
 *    trade-off, not a security hole, for the low-value abuse this guards
 *    against (spam/log noise on unauthenticated form endpoints).
 *  - This does NOT span multiple workers/processes. If the deployment ever
 *    moves to a multi-process or multi-instance model (e.g. a process
 *    manager running >1 Node worker, or horizontal scaling across servers),
 *    each process gets its own counters and the effective limit multiplies
 *    by the worker count. At that point this needs a shared store (Redis,
 *    etc.) instead.
 */

type Timestamps = number[]

const MAX_TRACKED_KEYS = 5000

const hits = new Map<string, Timestamps>()

export type RateLimitOptions = {
  /** Max allowed requests within the window. */
  limit: number
  /** Window size in milliseconds. */
  windowMs: number
  /** Injectable clock for tests; defaults to Date.now(). */
  now?: number
}

export type RateLimitResult = {
  allowed: boolean
  remaining: number
  retryAfterSeconds: number
}

/**
 * Pure, testable core. Keyed by an arbitrary string (route + client identity).
 * Prunes timestamps outside the window on every call so a key's memory
 * footprint never exceeds `limit` entries.
 */
export function checkRateLimit(key: string, opts: RateLimitOptions): RateLimitResult {
  const now = opts.now ?? Date.now()
  const windowStart = now - opts.windowMs

  let timestamps = hits.get(key)
  if (timestamps) {
    // refresh recency for the key-cap eviction below
    hits.delete(key)
  }
  timestamps = (timestamps ?? []).filter((t) => t > windowStart)

  if (timestamps.length >= opts.limit) {
    hits.set(key, timestamps)
    const oldest = timestamps[0]
    const retryAfterMs = oldest + opts.windowMs - now
    return {
      allowed: false,
      remaining: 0,
      retryAfterSeconds: Math.max(1, Math.ceil(retryAfterMs / 1000)),
    }
  }

  timestamps.push(now)
  hits.set(key, timestamps)
  evictOldestIfOverCap()

  return {
    allowed: true,
    remaining: opts.limit - timestamps.length,
    retryAfterSeconds: 0,
  }
}

/**
 * Bounds total memory: if a hostile actor rotates through many distinct
 * keys (e.g. IP rotation), the oldest-touched key is evicted rather than
 * letting the Map grow without bound. Map iteration order is insertion
 * order, and `checkRateLimit` re-inserts on every touch, so the first
 * entry is always the least-recently-used key.
 */
function evictOldestIfOverCap(): void {
  while (hits.size > MAX_TRACKED_KEYS) {
    const oldestKey = hits.keys().next().value
    if (oldestKey === undefined) break
    hits.delete(oldestKey)
  }
}

/** Test-only: clears all tracked state between test cases. */
export function resetRateLimits(): void {
  hits.clear()
}

/**
 * Per-route limits for the public form endpoints. Chosen conservatively for
 * a small cooperative site with low legitimate traffic, high enough that a
 * genuine user can submit a form, hit a validation error, fix it, and retry
 * without being blocked:
 *
 *  - membership: unauthenticated, no captcha (product decision), and
 *    forwards into the ProcessWire/intranet system-of-record — the
 *    strictest limit, since each accepted submission has a real downstream
 *    write cost.
 *  - contact / visit / waiting-list / event-signup: captcha-protected
 *    already, so rate limiting here is a second layer against captcha
 *    solver farms / outage-mode brute forcing, not the primary defense.
 *  - subscribe: no captcha, but low value/risk (mailing list opt-in) and
 *    idempotent-ish (worst case a duplicate signup), so a slightly higher
 *    ceiling than membership is acceptable.
 */
export const RATE_LIMITS = {
  membership: { limit: 3, windowMs: 10 * 60 * 1000 }, // 3 / 10 min
  contact: { limit: 5, windowMs: 5 * 60 * 1000 }, // 5 / 5 min
  subscribe: { limit: 5, windowMs: 10 * 60 * 1000 }, // 5 / 10 min
  visit: { limit: 5, windowMs: 10 * 60 * 1000 }, // 5 / 10 min
  'waiting-list': { limit: 5, windowMs: 10 * 60 * 1000 }, // 5 / 10 min
  'event-signup': { limit: 5, windowMs: 10 * 60 * 1000 }, // 5 / 10 min
} as const

export type RateLimitedRoute = keyof typeof RATE_LIMITS

export const RATE_LIMIT_ERROR_MESSAGE = 'Zu viele Anfragen. Bitte versuche es in einigen Minuten erneut.'

const FALLBACK_KEY = 'unknown-client'

/**
 * Derives the client IP the same way the existing form routes already do
 * (see the duplicated `getClientIp` in each form route under
 * `app/api/forms/`): first entry of `x-forwarded-for`, trimmed, or null.
 * Reusing that exact
 * precedence keeps rate-limit identity consistent with what Turnstile's
 * `remoteip` already sees for the same request.
 *
 * When no IP can be resolved (e.g. a stripped header), requests fall back to
 * a single shared bucket. That is an accepted trade-off: it means clients
 * genuinely behind the same NAT/proxy without a forwarded-for header share a
 * rate-limit budget, but that's strictly safer than not limiting them at
 * all, and this app already relies on `x-forwarded-for` for Turnstile.
 */
export function rateLimitKeyFromRequest(request: Request, routeName: RateLimitedRoute): string {
  const forwardedFor = request.headers.get('x-forwarded-for')
  const ip = forwardedFor ? forwardedFor.split(',')[0]?.trim() || null : null
  return `${routeName}:${ip || FALLBACK_KEY}`
}
