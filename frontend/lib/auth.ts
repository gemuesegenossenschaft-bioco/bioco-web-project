const CMS_BASE = process.env.NEXT_PUBLIC_PROCESSWIRE_BASE_URL || 'https://cms.bioco.ch'

interface PwSessionResult {
  loggedIn: boolean
  username: string
}

export async function checkPwSession(cookie: string | undefined): Promise<PwSessionResult | null> {
  try {
    const res = await fetch(`${CMS_BASE}/api/auth-check`, {
      credentials: 'include',
      headers: cookie ? { Cookie: cookie } : {},
    })
    if (!res.ok) return null
    const data = await res.json()
    if (data.loggedIn) return data as PwSessionResult
    return null
  } catch {
    return null
  }
}
