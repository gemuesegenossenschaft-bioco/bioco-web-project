import React from 'react'
import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@/hooks/useEventsFeed', () => ({
  useEventsFeed: () => ({ upcoming: [], past: [], isLoading: false, error: null }),
}))

import { EventsBanner } from '@/components/EventsBanner'
import { EventsSection } from '@/components/EventsSection'

describe.each([
  ['banner', (archiveUrl: string) => <EventsBanner archiveUrl={archiveUrl} archiveLabel="Alle Events ansehen" />],
  ['standard', (archiveUrl: string) => <EventsSection archiveUrl={archiveUrl} archiveLabel="Alle Events ansehen" />],
])('events archive URL safety: %s', (_name, renderComponent) => {
  it('renders a data-owned site-relative archive path', () => {
    render(renderComponent('/events-archive'))
    expect(screen.getByRole('link', { name: /Alle Events ansehen/ })).toHaveAttribute('href', '/events-archive')
  })

  it.each(['javascript:alert(1)', 'data:text/html,unsafe', '\njava\tscript:alert(1)', '//evil.example/path'])(
    'omits unsafe archive URL %s',
    (archiveUrl) => {
      render(renderComponent(archiveUrl))
      expect(screen.queryByRole('link', { name: /Alle Events ansehen/ })).toBeNull()
    },
  )
})
