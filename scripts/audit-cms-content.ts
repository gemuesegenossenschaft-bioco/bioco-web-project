#!/usr/bin/env npx tsx
/**
 * CMS Content Audit Script
 * Fetches every API endpoint and reports missing/empty content.
 * Run: npx tsx scripts/audit-cms-content.ts
 */

const BASE = process.env.SITE_URL || 'http://193.33.128.160:49152'

interface AuditResult {
  page: string
  issues: string[]
}

async function fetchJson(path: string) {
  const url = `${BASE}/api${path}`
  try {
    const res = await fetch(url, { signal: AbortSignal.timeout(10000) })
    if (!res.ok) return { error: `HTTP ${res.status}` }
    return await res.json()
  } catch (e: unknown) {
    return { error: (e as Error).message }
  }
}

async function checkImageUrl(url: string): Promise<boolean> {
  try {
    const res = await fetch(url, { method: 'HEAD', signal: AbortSignal.timeout(5000) })
    return res.ok
  } catch {
    return false
  }
}

async function auditPage(pageName: string): Promise<AuditResult> {
  const result: AuditResult = { page: pageName, issues: [] }
  const data = await fetchJson(`/page/${pageName}`)

  if (data.error) {
    result.issues.push(`API error: ${data.error}`)
    return result
  }

  // Check sections
  const sections = data.sections || []
  if (sections.length === 0) {
    result.issues.push('No sections found')
  }

  for (const section of sections) {
    if (!section.title && !section.text) {
      result.issues.push(`Section '${section.id}': empty (no title or text)`)
    }
    if (!section.image && (!section.media || section.media.length === 0)) {
      result.issues.push(`Section '${section.id}': no image`)
    }
    // Check image URLs
    if (section.image) {
      const ok = await checkImageUrl(section.image)
      if (!ok) result.issues.push(`Section '${section.id}': image URL broken (${section.image})`)
    }
    if (section.media) {
      for (const m of section.media) {
        const ok = await checkImageUrl(m.url)
        if (!ok) result.issues.push(`Section '${section.id}': media URL broken (${m.url})`)
      }
    }
  }

  return result
}

async function auditHomepage(): Promise<AuditResult> {
  const result: AuditResult = { page: 'homepage', issues: [] }
  const data = await fetchJson('/homepage')

  if (data.error) {
    result.issues.push(`API error: ${data.error}`)
    return result
  }

  if (!data.hero?.headline) result.issues.push('Missing hero headline')
  if (!data.hero?.image) result.issues.push('Missing hero image')

  const sections = data.sections || []
  if (sections.length === 0) result.issues.push('No homepage sections')

  for (const section of sections) {
    if (!section.title && !section.text) {
      result.issues.push(`Section '${section.id}': empty`)
    }
  }

  return result
}

async function auditEvents(): Promise<AuditResult> {
  const result: AuditResult = { page: 'events', issues: [] }
  const data = await fetchJson('/events')

  if (data.error) {
    result.issues.push(`API error: ${data.error}`)
    return result
  }

  const all = [...(data.upcoming || []), ...(data.past || [])]
  if (all.length === 0) result.issues.push('No events found')

  for (const event of all) {
    if (!event.eventType) result.issues.push(`Event '${event.title}': missing eventType`)
  }

  return result
}

async function main() {
  console.log(`\nCMS Content Audit (${BASE})\n${'='.repeat(50)}`)

  const pages = ['wir', 'mitmachen', 'solawi', 'gemuese', 'aktuelles']
  const results: AuditResult[] = []

  results.push(await auditHomepage())
  results.push(await auditEvents())

  for (const page of pages) {
    results.push(await auditPage(page))
  }

  let totalIssues = 0
  for (const r of results) {
    if (r.issues.length === 0) {
      console.log(`\n✓ ${r.page}: OK`)
    } else {
      console.log(`\n✗ ${r.page}: ${r.issues.length} issue(s)`)
      for (const issue of r.issues) {
        console.log(`  - ${issue}`)
      }
      totalIssues += r.issues.length
    }
  }

  console.log(`\n${'='.repeat(50)}`)
  console.log(`Total: ${totalIssues} issues across ${results.length} pages`)
  process.exit(totalIssues > 0 ? 1 : 0)
}

main()
