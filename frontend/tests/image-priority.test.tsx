import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

describe('Image priority', () => {
  it('HomeClient only uses priority on hero image, not on section images', () => {
    const content = fs.readFileSync(
      path.resolve(__dirname, '../components/HomeClient.tsx'),
      'utf-8'
    )
    // Count occurrences of 'priority' (as a prop on Image)
    const priorityMatches = content.match(/\bpriority\b/g) || []
    // Should have exactly 1 (hero only)
    expect(priorityMatches.length).toBe(1)
  })

  it('ItemDetailModal does not use priority on modal logo', () => {
    const content = fs.readFileSync(
      path.resolve(__dirname, '../components/ItemDetailModal.tsx'),
      'utf-8'
    )
    expect(content).not.toContain('priority')
  })
})
