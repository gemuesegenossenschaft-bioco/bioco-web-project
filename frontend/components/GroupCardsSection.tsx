'use client'

import Image from 'next/image'
import { useGroupCards } from '@/hooks/useGroupCards'
import { Card } from '@/components/ui/Card'

/**
 * CMS-driven group cards grid (Mitmachen "Gruppen & Gemeinschaft").
 * Card markup is byte-identical to the formerly hardcoded grid in
 * app/mitmachen/page.tsx; the data comes from /api/content/groups.
 */
export function GroupCardsSection() {
  const { groups } = useGroupCards()

  return (
    <div style={{
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
      gap: '24px',
      marginBottom: '32px'
    }}>
      {groups.map((group) => (
        <Card
          as="div"
          variant="soft"
          key={group.id}
          style={{
            background: 'var(--bg-primary, #fff)',
            borderRadius: '16px',
            overflow: 'hidden',
            boxShadow: '0 2px 8px rgba(0,0,0,0.06)'
          }}
        >
          <div style={{
            position: 'relative',
            width: '100%',
            aspectRatio: '4/3',
            // Dead declaration removed: --bioco-green-rgb is never defined
            // anywhere in the repo, so rgba(var(--bioco-green-rgb), …) was
            // invalid at computed-value time and the background resolved to
            // transparent. Removing it changes nothing visually; DEFINING the
            // token would have introduced a gradient that has never been on
            // the live site.
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          }}>
            {group.image ? (
              <Image
                src={group.image}
                alt={group.imageAlt || group.title}
                fill
                sizes="(max-width: 768px) 100vw, 33vw"
                style={{ objectFit: 'cover' }}
              />
            ) : (
              <span style={{ fontSize: '3rem', opacity: 0.6 }}>🌿</span>
            )}
          </div>
          <div style={{ padding: '20px' }}>
            <h3 style={{ fontSize: '1.25rem', marginBottom: '8px' }}>{group.title}</h3>
            {group.text ? (
              <div
                style={{ fontSize: '0.95rem', lineHeight: '1.6', color: 'var(--text-secondary)' }}
                dangerouslySetInnerHTML={{ __html: group.text }}
              />
            ) : null}
          </div>
        </Card>
      ))}
    </div>
  )
}
