import { NextResponse } from 'next/server'
import { getCurrentBuildId } from '@/lib/buildId'

export const dynamic = 'force-dynamic'

export async function GET() {
  const response = NextResponse.json({ buildId: getCurrentBuildId() }, { status: 200 })
  response.headers.set('Cache-Control', 'no-store')
  return response
}
