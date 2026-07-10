// D.2b — CSRF-prime-and-forward adapter for intranet.bioco.ch/my/signup/ (Django).
//
// Reproduces what a browser does against a CSRF-protected Django form:
//   1. GET the signup page, read the `csrftoken` cookie + hidden
//      `csrfmiddlewaretoken` input.
//   2. POST the mapped payload with that cookie, a Referer header, and the
//      token in the body.
//   3. Interpret 302 (or any 2xx without field errors) as success; a 200
//      re-render with Django `errorlist` markup as field errors.
//
// This never changes intranet.bioco.ch — see docs/prd-signup-integration.md.
// Field/error markup assumptions are PROVISIONAL (see lib/membership.ts).

const REQUEST_TIMEOUT_MS = 5000

export type ForwardToIntranetResult = {
  ok: boolean
  status?: number
  errors?: Record<string, string>
  error?: string
}

type ForwardToIntranetOptions = {
  fetchImpl?: typeof fetch
}

function extractCsrfCookie(headers: Headers): string | null {
  // undici exposes getSetCookie(); some environments (happy-dom, browsers)
  // filter `set-cookie` out of headers.get() entirely — try both.
  const viaGetSetCookie =
    typeof (headers as any).getSetCookie === 'function'
      ? ((headers as any).getSetCookie() as string[]).join('; ')
      : null
  const setCookieHeader = viaGetSetCookie || headers.get('set-cookie')
  if (!setCookieHeader) return null
  const match = setCookieHeader.match(/csrftoken=([^;]+)/)
  return match ? match[1] : null
}

function extractHiddenCsrfToken(html: string): string | null {
  const nameFirst = html.match(/name=["']csrfmiddlewaretoken["'][^>]*value=["']([^"']+)["']/)
  if (nameFirst) return nameFirst[1]
  const valueFirst = html.match(/value=["']([^"']+)["'][^>]*name=["']csrfmiddlewaretoken["']/)
  return valueFirst ? valueFirst[1] : null
}

// PROVISIONAL heuristic: Django's default `as_p`/`as_table` rendering emits
// `<ul class="errorlist"><li>message</li></ul>` immediately before the
// offending field's `name="..."` attribute. Unconfirmed against the real
// (auth-gated) intranet markup — revisit once that's enumerated.
function extractDjangoFieldErrors(html: string): Record<string, string> {
  const errors: Record<string, string> = {}
  const errorListRegex = /<ul class="errorlist[^"]*"[^>]*>\s*<li>([^<]+)<\/li>/g
  let match: RegExpExecArray | null
  let index = 0

  while ((match = errorListRegex.exec(html))) {
    const message = match[1].trim()
    const rest = html.slice(match.index + match[0].length, match.index + match[0].length + 500)
    const nameMatch = rest.match(/name=["']([a-zA-Z0-9_]+)["']/)
    const fieldName = nameMatch ? nameMatch[1] : `field_${index}`
    errors[fieldName] = message
    index += 1
  }

  return errors
}

function withTimeout<T>(run: (signal: AbortSignal) => Promise<T>): Promise<T> {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS)
  return run(controller.signal).finally(() => clearTimeout(timeout))
}

export async function forwardToIntranet(
  payload: Record<string, string>,
  opts: ForwardToIntranetOptions = {}
): Promise<ForwardToIntranetResult> {
  const url = process.env.INTRANET_SIGNUP_URL
  if (!url) {
    return { ok: false, error: 'intranet_signup_url_not_configured' }
  }

  const fetchImpl = opts.fetchImpl ?? fetch

  try {
    const primeResponse = await withTimeout((signal) =>
      fetchImpl(url, { method: 'GET', redirect: 'manual', signal })
    )

    const csrfToken = extractCsrfCookie(primeResponse.headers)
    const primeBody = await primeResponse.text()
    const csrfMiddlewareToken = extractHiddenCsrfToken(primeBody)

    if (!csrfToken || !csrfMiddlewareToken) {
      return { ok: false, error: 'csrf_prime_failed' }
    }

    const body = new URLSearchParams({
      ...payload,
      csrfmiddlewaretoken: csrfMiddlewareToken,
    })

    const forwardResponse = await withTimeout((signal) =>
      fetchImpl(url, {
        method: 'POST',
        redirect: 'manual',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          Cookie: `csrftoken=${csrfToken}`,
          Referer: url,
        },
        body: body.toString(),
        signal,
      })
    )

    if (forwardResponse.status >= 300 && forwardResponse.status < 400) {
      return { ok: true, status: forwardResponse.status }
    }

    if (forwardResponse.status >= 200 && forwardResponse.status < 300) {
      const text = await forwardResponse.text()
      const errors = extractDjangoFieldErrors(text)
      if (Object.keys(errors).length > 0) {
        return { ok: false, status: forwardResponse.status, errors }
      }
      return { ok: true, status: forwardResponse.status }
    }

    return { ok: false, status: forwardResponse.status, error: `unexpected_status_${forwardResponse.status}` }
  } catch (error: any) {
    return { ok: false, error: error?.message || 'network_error' }
  }
}
