'use client'

import { useEffect } from 'react'
import { buildStaleRecoveryUrl, shouldReloadForBuildChange } from '@/app/staleDeploymentRecovery'

interface BuildVersionWatcherProps {
  initialBuildId: string
}

export function BuildVersionWatcher({ initialBuildId }: BuildVersionWatcherProps) {
  useEffect(() => {
    if (typeof window === 'undefined' || !initialBuildId) return

    let isChecking = false

    const checkForNewBuild = async () => {
      if (isChecking) return
      isChecking = true

      try {
        const response = await fetch('/api/build-id', {
          cache: 'no-store',
          credentials: 'same-origin',
        })
        if (!response.ok) return

        const data = (await response.json()) as { buildId?: string }
        if (shouldReloadForBuildChange(initialBuildId, data.buildId)) {
          window.location.replace(buildStaleRecoveryUrl(window.location.href))
        }
      } catch {
        // Ignore temporary network failures.
      } finally {
        isChecking = false
      }
    }

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void checkForNewBuild()
      }
    }

    window.addEventListener('focus', checkForNewBuild)
    document.addEventListener('visibilitychange', handleVisibilityChange)
    const interval = window.setInterval(() => {
      if (document.visibilityState === 'visible') {
        void checkForNewBuild()
      }
    }, 30000)

    return () => {
      window.removeEventListener('focus', checkForNewBuild)
      document.removeEventListener('visibilitychange', handleVisibilityChange)
      window.clearInterval(interval)
    }
  }, [initialBuildId])

  return null
}
