import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

describe('Image optimization', () => {
  it('EventsSection does not use raw <img> for media', () => {
    const content = fs.readFileSync(
      path.resolve(__dirname, '../components/EventsSection.tsx'),
      'utf-8'
    )
    // Should not have eslint-disable for no-img-element
    expect(content).not.toContain('no-img-element')
    // Should import next/image
    expect(content).toContain("from 'next/image'")
  })

  it('AktuellesClient does not use raw <img> for media', () => {
    const content = fs.readFileSync(
      path.resolve(__dirname, '../components/AktuellesClient.tsx'),
      'utf-8'
    )
    expect(content).not.toContain('no-img-element')
    expect(content).toContain("from 'next/image'")
  })
})
