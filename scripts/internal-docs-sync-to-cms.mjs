#!/usr/bin/env node
/**
 * POST all internal-docs/*.md (except README) to ProcessWire internal-docs-sync.
 */
import { readdirSync, readFileSync, statSync } from 'fs'
import { join, extname } from 'path'
import { fileURLToPath } from 'url'
import { dirname } from 'path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const mirrorDir = join(__dirname, '..', 'internal-docs')

const base = process.env.CMS_BASE?.replace(/\/$/, '') || ''
const token = process.env.TOKEN || ''
if (!base || !token) {
  console.error('CMS_BASE and TOKEN env required')
  process.exit(1)
}

function walk(dir, prefix = '') {
  const out = {}
  for (const name of readdirSync(dir)) {
    if (name === 'README.md' || name === '.gitkeep') continue
    const p = join(dir, name)
    const rel = prefix ? `${prefix}/${name}` : name
    const st = statSync(p)
    if (st.isDirectory()) Object.assign(out, walk(p, rel))
    else if (extname(name) === '.md') out[rel] = readFileSync(p, 'utf8')
  }
  return out
}

const files = walk(mirrorDir)
if (Object.keys(files).length === 0) {
  console.log('No markdown files to sync')
  process.exit(0)
}

const url = `${base}/api/internal-docs-sync`
const res = await fetch(url, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Internal-Docs-Token': token,
  },
  body: JSON.stringify({ files, force: false }),
})

const text = await res.text()
let json
try {
  json = JSON.parse(text)
} catch {
  console.error(res.status, text)
  process.exit(1)
}

if (!res.ok || !json.success) {
  console.error(JSON.stringify(json, null, 2))
  process.exit(1)
}

console.log('Sync OK:', json.updated?.length || 0, 'updated', json.skipped?.length || 0, 'skipped')
