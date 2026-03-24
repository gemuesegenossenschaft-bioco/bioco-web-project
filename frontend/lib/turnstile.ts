const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify'

export type TurnstileVerifyResult = {
  ok: boolean
  errorCode?: string
}

type TurnstileResponse = {
  success?: boolean
  'error-codes'?: string[]
}

export async function verifyTurnstileToken(token: string, remoteIp?: string | null): Promise<TurnstileVerifyResult> {
  const secret = process.env.TURNSTILE_SECRET_KEY

  if (!secret) {
    console.error('TURNSTILE_SECRET_KEY is not configured')
    return { ok: false, errorCode: 'captcha_unavailable' }
  }

  if (!token || typeof token !== 'string') {
    return { ok: false, errorCode: 'captcha_missing' }
  }

  const body = new URLSearchParams({
    secret,
    response: token,
  })

  if (remoteIp) {
    body.set('remoteip', remoteIp)
  }

  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 5000)

  try {
    const response = await fetch(TURNSTILE_VERIFY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: body.toString(),
      signal: controller.signal,
    })

    if (!response.ok) {
      return { ok: false, errorCode: 'captcha_service_error' }
    }

    const result = (await response.json()) as TurnstileResponse

    if (result.success) {
      return { ok: true }
    }

    const firstError = result['error-codes']?.[0] || 'captcha_invalid'
    return { ok: false, errorCode: firstError }
  } catch (error) {
    console.error('Turnstile verification failed:', error)
    return { ok: false, errorCode: 'captcha_service_unreachable' }
  } finally {
    clearTimeout(timeout)
  }
}
