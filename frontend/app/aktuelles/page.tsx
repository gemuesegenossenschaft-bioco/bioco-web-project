import { getPageSections, getAktuelles } from '@/lib/processwire'
import { getAktuellesItems } from '@/components/AktuellesData'
import type { AktuellesItem } from '@/components/AktuellesData'
import type { AktuellesNewsItem } from '@/lib/processwire-types'
import { AktuellesClient } from '@/components/AktuellesClient'
import { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Aktuelles & Events | biocò Gemüsegenossenschaft Baden',
  description: 'Neuigkeiten, Schnuppertage und Events der biocò Gemüsegenossenschaft. Erlebe solidarische Landwirtschaft auf dem Geisshof.',
  keywords: 'aktuelles, events, schnuppertage, bioco, solidarische landwirtschaft, baden, brugg',
  openGraph: {
    title: 'Aktuelles & Events | biocò Gemüsegenossenschaft Baden',
    description: 'Neuigkeiten, Schnuppertage und Events der biocò Gemüsegenossenschaft.',
    type: 'website',
  },
}

// ISR: Revalidate every 60 seconds
export const revalidate = 60
export const dynamic = 'force-dynamic'

function mapNewsToAktuellesItem(item: AktuellesNewsItem): AktuellesItem {
  return {
    id: item.id,
    date: item.date,
    title: item.title,
    description: item.summary,
    type: 'aktuelles',
    fullDescription: item.body,
    imageUrl: item.image ?? undefined,
    url: item.url,
  }
}

export default async function AktuellesPage() {
  const [cmsSections, cmsNews] = await Promise.all([
    getPageSections('aktuelles'),
    getAktuelles(10),
  ])

  const aktuellesItems: AktuellesItem[] =
    cmsNews.length > 0
      ? cmsNews.map(mapNewsToAktuellesItem)
      : getAktuellesItems()

  return (
    <AktuellesClient sections={cmsSections} aktuellesItems={aktuellesItems} />
  )
}
