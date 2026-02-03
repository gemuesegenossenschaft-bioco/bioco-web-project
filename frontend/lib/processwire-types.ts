/**
 * ProcessWire API Response Types
 * 
 * Type definitions for the unified CMS API endpoints.
 * All types match the JSON structure returned by site/templates/api.php
 */

// ============================================================================
// SEO Types
// ============================================================================

export interface SeoImage {
  url: string;
  width?: number;
  height?: number;
}

export interface SeoRobots {
  index: boolean;
  follow: boolean;
}

export interface SeoData {
  title: string;
  description: string;
  canonical: string;
  ogImage?: SeoImage | null;
  robots: SeoRobots;
}

// ============================================================================
// Hero Types
// ============================================================================

export interface HeroContent {
  headline: string;
  subtitle: string;
  image: string | null;
  imageAlt: string;
}

export interface HeroResponse {
  hero: HeroContent;
}

// ============================================================================
// Section Types
// ============================================================================

export interface ContentButton {
  text: string;
  href: string;
  variant: 'primary' | 'secondary' | string;
}

export interface ContentMedia {
  url: string;
  alt: string;
  width?: number;
  height?: number;
  type: 'image' | 'video';
}

export interface ContentVideo {
  url: string;
  title?: string;
}

export interface ContentImage {
  url: string;
  alt: string;
}

export interface ContentSection {
  id: string;
  title: string;
  text: string;  // May contain HTML from CKEditor
  layout?: string;
  theme?: string;
  eyebrow?: string;
  component?: string;
  image?: string | null;
  imageAlt?: string;
  imageData?: ImageData | null;
  images?: ContentImage[];  // For sections with multiple images
  media?: ContentMedia[];
  video?: ContentVideo | null;
  buttons?: ContentButton[];
  imageOverlay?: 'none' | 'dark' | 'green' | 'orange';  // Image tint/overlay
  bgColor?: 'none' | 'green' | 'darkgreen' | 'orange' | 'gray' | 'white';  // Section background
}

export interface SectionsResponse {
  page: string;
  sections: ContentSection[];
  seo?: SeoData;
}

// ============================================================================
// Homepage Types
// ============================================================================

export interface HomepageContent {
  hero: HeroContent;
  sections: ContentSection[];
  seo?: SeoData;
}

export interface HomepageResponse extends HomepageContent {}

// ============================================================================
// Group Card Types (for Mitmachen page)
// ============================================================================

export interface GroupCard {
  id: string;
  title: string;
  text: string;  // May contain HTML
  image: string | null;
  imageAlt: string;
}

export interface GroupsResponse {
  groups: GroupCard[];
}

// ============================================================================
// Page Types
// ============================================================================

export interface ImageData {
  url: string;
  description: string;
  width?: number;
  height?: number;
}

export interface PageSection {
  id?: string;
  title?: string;
  content?: string;
}

export interface PageData {
  id: number;
  title: string;
  url: string;
  template?: string;
  body?: string;
  hero_title?: string;
  hero_subtitle?: string;
  summary?: string;
  cta_text?: string;
  cta_url?: string;
  logo_image?: ImageData;
  hero_image?: ImageData;
  sidebar_content?: string;
  gallery_images?: ImageData[];
  footer_content?: string;
  css_variant?: string;
  sections?: ContentSection[] | PageSection[];
  children?: PageData[];
  seo?: SeoData;
}

export interface PageIndexItem {
  id: number;
  title: string;
  path: string;
  url: string;
  template?: string;
  seo?: SeoData;
}

export interface PageIndexResponse {
  success: boolean;
  items: PageIndexItem[];
  count: number;
}

// ============================================================================
// Navigation Types
// ============================================================================

export interface NavigationItem {
  id: number;
  title: string;
  url: string;
  sort?: number;  // Sort order from page tree
}

// ============================================================================
// Events Types
// ============================================================================

export interface EventMedia {
  url: string;
  description: string;
  type: 'image' | 'video';
}

export interface EventItem {
  id: number;
  title: string;
  description: string;
  fullDescription: string;
  location: string;
  startDate: string | null;
  endDate: string | null;
  dateLabel: string;
  timeLabel: string;
  signupEnabled: boolean;
  signupNotes: string;
  status: 'upcoming' | 'past';
  media: EventMedia[];
  url: string;
  parentTitle: string;
}

export interface EventsResponse {
  success: boolean;
  generatedAt: string;
  upcoming: EventItem[];
  past: EventItem[];
}

// ============================================================================
// Aktuelles/News Types
// ============================================================================

export interface AktuellesNewsItem {
  id: number;
  title: string;
  summary: string;
  body: string;
  date: string;
  image: string | null;
  url: string;
}

export interface AktuellesResponse {
  success: boolean;
  items: AktuellesNewsItem[];
  count: number;
}

// ============================================================================
// Instagram Types
// ============================================================================

export interface InstagramPost {
  id: number;
  title: string;
  body: string;
  date: string;
  url: string;
  instagram_id?: string;
  instagram_url?: string;
  imageUrl?: string;
}

export interface InstagramResponse {
  success: boolean;
  posts: InstagramPost[];
  count: number;
}

// ============================================================================
// Health Check Types
// ============================================================================

export interface HealthResponse {
  status: 'ok';
  timestamp: number;
  version?: string;
}

// ============================================================================
// Form Types
// ============================================================================

export interface FormResponse {
  success: boolean;
  error?: string;
}

export interface DoiConfirmResponse {
  success: boolean;
  form_type?: string;
  error?: string;
}
