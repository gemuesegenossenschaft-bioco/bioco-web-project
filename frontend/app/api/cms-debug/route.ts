import { NextResponse } from 'next/server'
import { buildCmsHeaders, cmsApiUrl, processwireBaseUrl } from '@/lib/cmsClient'

export async function GET() {
  const path = '/content/sections/wir'
  const requestUrl = cmsApiUrl(path)

  try {
    const response = await fetch(requestUrl, {
      headers: buildCmsHeaders(),
      cache: 'no-store',
    })

    const status = response.status
    const ok = response.ok
    const data = ok ? await response.json() : null
    const errorBody = ok ? null : await response.text()

    return NextResponse.json({
      baseUrl: processwireBaseUrl,
      requestUrl,
      status,
      ok,
      sectionsCount: data?.sections?.length || 0,
      sectionIds: data?.sections?.map((section: { id: string }) => section.id) || [],
      error: errorBody,
    })
  } catch (error) {
    return NextResponse.json({
      baseUrl: processwireBaseUrl,
      requestUrl,
      ok: false,
      error: error instanceof Error ? error.message : 'Unknown error',
    })
  }
}
