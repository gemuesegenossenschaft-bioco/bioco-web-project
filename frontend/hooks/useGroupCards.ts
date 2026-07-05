'use client'

import { useEffect, useState } from 'react'
import { cmsApiUrl } from '@/lib/cmsClient'
import type { GroupCard, GroupsResponse } from '@/lib/processwire-types'

interface GroupCardsState {
  groups: GroupCard[]
  isLoading: boolean
}

/**
 * Client-side fetch of the Mitmachen group cards (/api/content/groups).
 * Mirrors useEventsFeed: SectionRenderer is a client component, so the
 * group_cards registered component loads its data in the browser.
 */
export function useGroupCards(): GroupCardsState {
  const [state, setState] = useState<GroupCardsState>({ groups: [], isLoading: true })

  useEffect(() => {
    let isMounted = true

    fetch(cmsApiUrl('/content/groups'), { cache: 'no-store' })
      .then((response) => {
        if (!response.ok) throw new Error(`groups endpoint responded with ${response.status}`)
        return response.json() as Promise<GroupsResponse>
      })
      .then((data) => {
        if (!isMounted) return
        setState({ groups: Array.isArray(data?.groups) ? data.groups : [], isLoading: false })
      })
      .catch(() => {
        if (!isMounted) return
        setState({ groups: [], isLoading: false })
      })

    return () => {
      isMounted = false
    }
  }, [])

  return state
}
