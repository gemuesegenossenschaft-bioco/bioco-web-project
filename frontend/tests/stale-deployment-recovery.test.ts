import { describe, expect, it } from 'vitest'
import { buildStaleRecoveryUrl, isStaleDeploymentError } from '@/app/staleDeploymentRecovery'

describe('staleDeploymentRecovery', () => {
  it('detects stale server action errors', () => {
    expect(
      isStaleDeploymentError({
        message: 'Failed to find Server Action "x". This request might be from an older deployment.',
      }),
    ).toBe(true)
  })

  it('detects digest mismatch style errors', () => {
    expect(
      isStaleDeploymentError({
        message: "TypeError: Cannot read properties of null (reading 'digest')",
      }),
    ).toBe(true)
  })

  it('ignores unrelated errors', () => {
    expect(
      isStaleDeploymentError({
        message: 'Network error',
      }),
    ).toBe(false)
  })

  it('adds cache-busting query while keeping hash', () => {
    expect(buildStaleRecoveryUrl('https://bioco.ch/mitmachen?x=1#top', 123)).toBe(
      '/mitmachen?x=1&__fresh=123#top',
    )
  })
})
