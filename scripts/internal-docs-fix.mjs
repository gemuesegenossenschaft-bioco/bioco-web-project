#!/usr/bin/env node
/**
 * Placeholder for deterministic doc maintenance (lint, link fixes, regex rules).
 * Add rules here; exit 0 even when no changes.
 */
import { readdirSync, readFileSync, writeFileSync, statSync } from 'fs'
import { join, extname } from 'path'
import { fileURLToPath } from 'url'
import { dirname } from 'path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const mirrorDir = join(__dirname, '..', 'internal-docs')

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    if (name === 'README.md' || name === '.gitkeep') continue
    const p = join(dir, name)
    const st = statSync(p)
    if (st.isDirectory()) walk(p, out)
    else if (extname(name) === '.md') out.push(p)
  }
  return out
}

let changed = 0
try {
  const files = walk(mirrorDir)
  for (const file of files) {
    let s = readFileSync(file, 'utf8')
    const before = s
    // Example: normalize trailing newline
    s = s.replace(/\r\n/g, '\n')
    if (!s.endsWith('\n')) s += '\n'
    if (s !== before) {
      writeFileSync(file, s, 'utf8')
      changed++
    }
  }
} catch (e) {
  if (e.code === 'ENOENT') {
    process.exit(0)
  }
  throw e
}

console.log('internal-docs-fix: touched', changed, 'files')
