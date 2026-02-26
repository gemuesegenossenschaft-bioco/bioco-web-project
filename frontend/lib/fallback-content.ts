/**
 * Fallback Content
 * 
 * Static fallback content for when CMS is unavailable.
 * This ensures the site remains functional even if the API fails.
 * 
 * All content here mirrors the CMS content structure and should be
 * kept in sync with ProcessWire when making significant content changes.
 */

import type { ContentSection, GroupCard, HeroContent, HomepageContent } from './processwire-types'

// ============================================================================
// Homepage Fallbacks
// ============================================================================

export const FALLBACK_HERO: HeroContent = {
  headline: 'Gemeinsam Gemüse anbauen und geniessen',
  subtitle: 'Solidarische Landwirtschaft in der Region Baden-Brugg',
  image: 'https://cms.bioco.ch/site/assets/files/1/frontseitestartseite.jpg',
  imageAlt: 'Solidarische Landwirtschaft auf dem Feld',
}

export const FALLBACK_HOMEPAGE_SECTIONS: ContentSection[] = [
  {
    id: 'willkommen',
    title: 'Willkommen bei biocò',
    text: `<p>Bei der biocò Gemüsegenossenschaft teilen wir nicht nur die Ernte, sondern auch die Verantwortung und die Freude an der Arbeit. Das ist <a href="/solawi">solidarische Landwirtschaft</a> in der Region Baden: Produzentinnen und Konsumentinnen arbeiten Hand in Hand, gestalten gemeinsam den Anbau und erleben, wie aus einem Samen frisches Bio-Gemüse wird, das wöchentlich in den <a href="/standorte-depots">Depots in Baden, Brugg und Gebenstorf</a> abgeholt werden kann.</p>`,
    image: 'https://cms.bioco.ch/site/assets/files/1778/zusammen-arbeiten.jpg',
    imageAlt: 'Gemeinschaft bei solidarischer Landwirtschaft biocò Baden-Brugg',
    buttons: [
      { text: 'Lerne uns kennen', href: '/wir', variant: 'primary' },
    ],
  },
  {
    id: 'gemeinsam',
    title: 'Gemeinsam, solidarisch, frisch',
    text: `<p>Seit 2014 bewirtschaften wir den <a href="/wir">Geisshof in Gebenstorf</a> nach biologisch-dynamischen Prinzipien und liefern <a href="/gemuese">Demeter-Gemüse</a> in höchster Bio-Qualität. Hier wächst Woche für Woche eine vielfältige Auswahl an saisonalem Gemüse aus <a href="/solawi">solidarischer Landwirtschaft</a>, das wir gemeinsam anbauen, pflegen und ernten. Jedes Mitglied bringt sich ein, ob auf dem Feld, in der Logistik oder bei der Organisation.</p>`,
    image: 'https://cms.bioco.ch/site/assets/files/1779/gemeinsamsolidarischfrisch.jpg',
    imageAlt: 'Frisch geerntetes Demeter-Gemüse vom Geisshof',
    buttons: [
      { text: 'Was gerade wächst', href: '/gemuese', variant: 'secondary' },
    ],
  },
  {
    id: 'kennenlernen',
    title: 'Möchtest du uns kennenlernen?',
    text: `<p>Es können viele Fragen auftauchen, die wir auf dieser Website nicht allesamt beantworten können. Du hast die Möglichkeit, den Hof und uns an den regulären Schnuppertagen kennenzulernen. Oder du kannst dich via Kontaktformular bei uns melden und wir beantworten deine Fragen persönlich.</p>`,
    buttons: [
      { text: 'Nimm Kontakt auf', href: '/kontakt', variant: 'primary' },
      { text: 'Zu uns finden', href: '/standorte-depots', variant: 'secondary' },
    ],
  },
]

export const FALLBACK_HOMEPAGE: HomepageContent = {
  hero: FALLBACK_HERO,
  sections: FALLBACK_HOMEPAGE_SECTIONS,
}

// ============================================================================
// Mitmachen Page Fallbacks
// ============================================================================

export const FALLBACK_MITMACHEN_INTRO = {
  title: 'Mitmachen bei biocò',
  text: `<p>Werde Teil unserer Gemüsegenossenschaft und erlebe <a href="/solawi">solidarische Landwirtschaft</a> hautnah. Hier erfährst du, wie du dich einbringen kannst und was Mitarbeit bei biocò bedeutet.</p>`,
}

export const FALLBACK_MITMACHEN_SECTIONS: ContentSection[] = [
  {
    id: 'mitarbeit',
    title: 'Was es braucht, damit wir gesundes Gemüse haben',
    text: `<p>Jedes Mitglied bringt sich ein und unterstützt die Genossenschaft aktiv. Die Mitarbeit ist ein wichtiger Teil unserer <a href="/solawi">solidarischen Landwirtschaft</a>.</p>`,
  },
  {
    id: 'familien',
    title: 'Familien & Kinder auf dem Geisshof',
    text: `<p>Familien und Kinder sind sehr regelmässige Helfer auf dem Geisshof. Die Einbindung von Kindern in den Prozess des Gemüseanbaus ist ein zentraler Bestandteil der biocò-Kultur.</p>
<p>Auf dem Geisshof erleben Kinder hautnah, wie Gemüse wächst, gepflegt wird und geerntet wird. Sie lernen spielerisch den Kreislauf der Natur kennen und entwickeln ein tiefes Verständnis für die Herkunft ihrer Nahrung.</p>`,
    image: 'https://cms.bioco.ch/site/assets/files/1781/bioco_ernte-kurbis-hoch.jpg',
    imageAlt: 'Frisch geerntetes Demeter-Gemüse vom Geisshof',
  },
]

export const FALLBACK_GROUP_CARDS: GroupCard[] = [
  {
    id: 'elki',
    title: 'Elki',
    text: '<p>Familienaktivitäten und gemeinsame Anlässe. Die Elki-Gruppe organisiert speziell für Familien mit Kindern ausgerichtete Aktivitäten auf dem Hof.</p>',
    image: 'https://cms.bioco.ch/site/assets/files/1778/zusammen-arbeiten.jpg',
    imageAlt: 'Elki Familienaktivitäten bei biocò',
  },
  {
    id: 'kraeutergruppe',
    title: 'Kräutergruppe',
    text: '<p>Spezialisiert auf Kräuter und Gewürze. Diese Gruppe widmet sich dem Anbau, der Pflege und der Verarbeitung von Kräutern.</p>',
    image: null,
    imageAlt: 'Kräutergruppe',
  },
  {
    id: 'betriebsgruppe',
    title: 'BG (Betriebsgruppe)',
    text: '<p>Aktive Mitarbeit in der Betriebsorganisation. Die Betriebsgruppe koordiniert strategische Entscheidungen und plant die Anbauzyklen.</p>',
    image: 'https://cms.bioco.ch/site/assets/files/1713/zusammen-arbeiten.jpg',
    imageAlt: 'Betriebsgruppe der Gemüsegenossenschaft biocò Gebenstorf',
  },
]

// ============================================================================
// Gemüse Page Fallbacks
// ============================================================================

export const FALLBACK_GEMUESE_INTRO = {
  title: 'Unser Gemüse',
  text: `<p>Auf dem Geisshof in Gebenstorf bauen wir nach biologisch-dynamischen Prinzipien (Demeter) eine grosse Vielfalt an saisonalem Gemüse an. Jede Woche gibt es eine frische Auswahl, die unsere Mitglieder in den Depots abholen.</p>`,
}

// ============================================================================
// Solawi Page Fallbacks
// ============================================================================

export const FALLBACK_SOLAWI_INTRO = {
  title: 'Solidarische Landwirtschaft',
  text: `<p>Solidarische Landwirtschaft (Solawi) ist ein alternatives Wirtschaftsmodell, bei dem Produzent*innen und Konsument*innen eine direkte Partnerschaft eingehen. Bei biocò teilen wir nicht nur die Ernte, sondern auch die Verantwortung, das Risiko und die Freude an der gemeinsamen Arbeit.</p>`,
}

export const FALLBACK_SOLAWI_SECTIONS: ContentSection[] = [
  {
    id: 'prinzipien',
    title: 'Die Prinzipien der Solawi',
    text: `<ul>
<li><strong>Gemeinsame Verantwortung:</strong> Alle tragen zum Gelingen bei</li>
<li><strong>Faire Preise:</strong> Bauern erhalten einen gerechten Lohn</li>
<li><strong>Saisonal & regional:</strong> Frisches Gemüse aus der Region</li>
<li><strong>Transparenz:</strong> Jeder weiss, woher das Essen kommt</li>
</ul>`,
  },
]

// ============================================================================
// Aktuelles Page Fallbacks
// ============================================================================

export const FALLBACK_AKTUELLES_INTRO = {
  title: 'Aktuelles',
  text: `<p>Neuigkeiten, Veranstaltungen und Updates aus der biocò Gemüsegenossenschaft.</p>`,
}

// ============================================================================
// Wir Page Fallbacks
// ============================================================================

export const FALLBACK_WIR_INTRO = {
  title: 'biocò: Die Gemüsegenossenschaft',
  text: `<p>Seit 2014 bewirtschaften wir einen Bio Bauernhof auf dem Geisshof in Gebenstorf. Lerne unser Team, unsere Geschichte und die Werte kennen, die unsere <a href="/solawi">solidarische Landwirtschaft</a> prägen.</p>`,
}

export const FALLBACK_WIR_SECTIONS: ContentSection[] = [
  {
    id: 'wir',
    title: 'Wir',
    text: `<p>biocò ist eine Gemeinschaft von engagierten Menschen, die gemeinsam für frisches, regionales <a href="/gemuese">Demeter-Gemüse</a> sorgen.</p>`,
  },
  {
    id: 'geisshof',
    title: 'Der Geisshof',
    text: `<p>Wir bewirtschaften einen Bio Bauernhof in Baden – genauer gesagt den Geisshof in Gebenstorf im Aargau. Seit 2014 ist dieser Ort das Herzstück von biocò, wo wir Bio-Gemüse in Demeter-Qualität anbauen. Zentral gelegen zwischen Baden und Brugg versorgen wir die Region mit frischem, saisonalem Gemüse.</p>`,
  },
  {
    id: 'mission',
    title: 'Mission & Leitbild',
    text: '',
  },
  {
    id: 'geschichte',
    title: 'Geschichte',
    text: `<p>Die Gemüsegenossenschaft biocò wurde 2014 in Gebenstorf im Aargau gegründet. Aus einer kleinen Gruppe engagierter Menschen aus Baden, Brugg und der Region wurde eine lebendige Gemeinschaft, die solidarische Landwirtschaft lebt.</p>
<p>Gestartet wurde auf dem Geisshof in Gebenstorf, wo wir bis heute unser Gemüse anbauen. Über die Jahre haben wir die Anbaufläche erweitert, neue Standorte (Depots) für die Gemüseabholung geschaffen und die Strukturen der Genossenschaft weiterentwickelt.</p>
<p>Heute versorgen wir Mitglieder in der Region Baden-Brugg wöchentlich mit frischem, saisonalem Demeter-Gemüse.</p>`,
  },
]

// ============================================================================
// Helper: Merge CMS content with fallbacks
// ============================================================================

/**
 * Merge CMS content with fallback, preferring CMS values when available
 */
export function mergeWithFallback<T extends object>(
  cmsContent: T | null | undefined,
  fallback: T
): T {
  if (!cmsContent) return fallback
  
  return {
    ...fallback,
    ...cmsContent,
  }
}

/**
 * Merge sections arrays, using CMS if available and non-empty
 */
export function mergeSections(
  cmsSections: ContentSection[] | null | undefined,
  fallbackSections: ContentSection[]
): ContentSection[] {
  if (cmsSections && cmsSections.length > 0) {
    return cmsSections
  }
  return fallbackSections
}

/**
 * Get section by ID from either CMS or fallback
 */
export function getSectionById(
  sectionId: string,
  cmsSections: ContentSection[] | null | undefined,
  fallbackSections: ContentSection[]
): ContentSection | undefined {
  const sections = cmsSections && cmsSections.length > 0 ? cmsSections : fallbackSections
  return sections.find(s => s.id === sectionId)
}
