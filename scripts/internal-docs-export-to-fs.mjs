#!/usr/bin/env node
/**
 * Reads export.json from stdin or first arg path; writes files under internal-docs/
 */
import { readFileSync, mkdirSync, writeFileSync } from 'fs'
import { dirname, join } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = join(__dirname, '..')
const mirrorDir = join(root, 'internal-docs')

const inputPath = process.argv[2] || join(root, 'export.json')
const raw = readFileSync(inputPath, 'utf8')
const data = JSON.parse(raw)
if (!data.files || typeof data.files !== 'object') {
  console.error('export.json missing files object')
  process.exit(1)
}

for (const rel of Object.keys(data.files)) {
  if (!/^[a-zA-Z0-9_./-]+\.md$/.test(rel)) continue
  const full = join(mirrorDir, rel)
  mkdirSync(dirname(full), { recursive: true })
  writeFileSync(full, data.files[rel], 'utf8')
}

console.log('Wrote', Object.keys(data.files).length, 'files to internal-docs/')
