'use client'

import { useEffect, useState } from 'react'
import {
  AktuellesItem,
  EventsFeed,
  fetchEventsFromCms,
  getFallbackEventsFeed,
} from '@/components/AktuellesData'

interface EventsState extends EventsFeed {
  isLoading: boolean
  error?: string
}

export function useEventsFeed(limit?: number) {
  const fallback = getFallbackEventsFeed()
  const [state, setState] = useState<EventsState>({
    ...fallback,
    isLoading: true,
  })

  useEffect(() => {
    let isMounted = true

    fetchEventsFromCms()
      .then((feed) => {
        if (!isMounted) return
        
        // If API returns empty results, use fallback
        if (feed.upcoming.length === 0 && feed.past.length === 0) {
          console.info('API returned no events, using static fallback data')
          setState({
            ...fallback,
            isLoading: false,
          })
        } else {
          setState({
            ...feed,
            isLoading: false,
          })
        }
      })
      .catch((error) => {
        console.warn('Events feed failed. Using fallback data.', error)
        if (!isMounted) return
        setState({
          ...fallback,
          isLoading: false,
          // Don't show error to user if we have fallback data
        })
      })

    return () => {
      isMounted = false
    }
  }, [])

  const limitedUpcoming = limit
    ? state.upcoming.slice(0, limit)
    : state.upcoming

  return {
    ...state,
    upcoming: limitedUpcoming,
  }
}

