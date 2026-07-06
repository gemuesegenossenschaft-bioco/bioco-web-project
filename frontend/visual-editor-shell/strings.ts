/**
 * Every user-facing (German) UI string of the visual-editor shell, extracted
 * verbatim from the pre-rebuild site/templates/visual-editor.php (git history:
 * the 3782-line IIFE version). Single source of truth for shell copy.
 *
 * Strings that belonged exclusively to removed features are NOT here:
 * - page picker ("Seite suchen...", "Keine passende Seite gefunden.", the
 *   "n / m" counter) — the picker is gone by design (CLAUDE.md: iframe
 *   navigation drives pages).
 * - server-side draft layer ("Server-Entwurf wiederhergestellt.",
 *   "Server-Entwurf veraltet und verworfen.", "Entwurf konnte nicht
 *   gespeichert werden") — draft persistence is sessionStorage-only now.
 * Two empty-state strings that told the user to pick a page "links" (in the
 * removed picker) were reworded to reference iframe navigation only; see
 * `emptyNoPage` / `sidebarPathPlaceholder`.
 */

export const STRINGS = {
  /* Toolbar / global */
  toolbarLogo: 'bioco Visual Editor',
  statusDisconnected: 'Nicht verbunden',
  statusConnected: 'Verbunden',
  modeEdit: 'Edit',
  modeBrowse: 'Browse',
  btnRefresh: 'Neu laden',
  btnPresets: 'Vorlagen',
  btnPwAdmin: 'PW Admin',
  btnBack: 'Zurück',

  /* Sidebar header */
  sidebarKickerPage: 'Seite',
  sidebarDefaultTitle: 'Startseite',
  sidebarPathPlaceholder: 'In der Vorschau navigieren, um eine Seite zu bearbeiten.',
  btnAddSection: 'Abschnitt hinzufügen',
  pageNotEditable: 'Nicht bearbeitbar',
  pageEditableFallback: 'Bearbeitbare Seite',
  pageUnavailable: 'Seite im Visual Editor nicht verfügbar',

  /* Section list */
  emptyNoPage: 'In der Vorschau navigieren, um eine Seite zu bearbeiten.',
  emptyNoSections: 'Noch keine Abschnitte vorhanden. Füge rechts oben einen Abschnitt hinzu.',
  emptyNoSelection: 'Wähle einen Abschnitt oder ein Feld direkt in der Vorschau.',
  dirtyPill: 'UNGESPEICHERT',
  untitledSection: '(kein Titel)',
  duplicateTitle: 'Abschnitt kopieren',
  deleteTitle: 'Abschnitt löschen',
  copySuffix: ' (Kopie)',
  newSectionTitle: 'Neuer Abschnitt',

  /* Actions bar */
  btnDiscard: 'Entwurf verwerfen',
  btnPublish: 'Publizieren',
  btnPublishing: 'Publiziert...',

  /* Status messages */
  statusLoadingSections: 'Abschnitte laden...',
  busyLoadingSections: 'Abschnitte laden…',
  statusLoadingPreview: 'Vorschau laden...',
  busyLoadingPreview: 'Vorschau laden…',
  statusPreviewFailed: 'Vorschau konnte nicht verbunden werden',
  statusDraftSaved: 'Entwurf lokal gespeichert',
  statusDraftDiscarded: 'Entwurf verworfen',
  statusPublished: 'Publiziert',
  statusPublishedLive: 'Publiziert & live',
  statusPublishedStaleBuild: 'Publiziert, aber Build nicht aktualisiert',
  errorPublishFailed: 'Publizieren fehlgeschlagen',
  errorLoadFailed: 'Fehler beim Laden',
  busyPublishing: 'Änderungen publizieren…',
  statusUndo: 'Rückgängig',
  statusRedo: 'Wiederhergestellt',
  statusStaleDraftDiscarded: 'Veralteter Entwurf verworfen, weil die Seite inzwischen geändert wurde.',
  statusDraftRestored: 'Lokaler Entwurf wiederhergestellt.',
  statusConflictsResolved: 'Konflikte gelöst. Bitte prüfen und erneut publizieren.',
  statusServerChangesAdopted: 'Serveränderungen übernommen. Bitte erneut publizieren.',
  statusConflictsRetry: 'Konflikte gelöst. Erneut publizieren.',
  statusOrderUpdated: 'Reihenfolge im Entwurf aktualisiert',
  statusSectionAdded: 'Abschnitt zum Entwurf hinzugefügt',
  statusSectionDeleted: 'Abschnitt im Entwurf gelöscht',
  statusSectionDuplicated: 'Abschnitt im Entwurf dupliziert. Medien prüfen.',
  statusMediaSelected: 'Medium im Entwurf ausgewählt',
  statusPresetInserted: 'Vorlage eingefügt',
  /* Verbatim from the old file (including the missing umlaut). */
  statusTypeAddedSuffix: ' hinzugefuegt',

  /* Busy overlay */
  busyDefault: 'Bitte warten…',
  busyBody: 'Der Editor verarbeitet gerade deine Aktion. Andere Interaktionen sind kurz gesperrt.',

  /* Confirms / guards */
  confirmDiscard: 'Lokalen Entwurf wirklich verwerfen?',
  confirmDirtyAction: (actionLabel: string) =>
    `Ungespeicherte Änderungen vorhanden. "${actionLabel}" trotzdem ausführen?`,
  confirmDeleteSection: (title: string) => `Abschnitt "${title}" wirklich löschen?`,
  confirmFieldConflict: (sectionId: string, field: string) =>
    `Konflikt in Abschnitt "${sectionId}" Feld "${field}". OK = lokal behalten, Abbrechen = Server übernehmen.`,
  confirmOrderConflict:
    'Abschnittsreihenfolge-Konflikt. OK = lokale Reihenfolge behalten, Abbrechen = Server-Reihenfolge übernehmen.',
  actionReload: 'Neu laden',
  actionPageSwitch: 'Seitenwechsel',

  /* ProcessWire focus */
  alertPwFocusNeedsCleanDraft:
    'ProcessWire-Fokus ist nur ohne offenen Entwurf verfügbar. Bitte zuerst publizieren oder den Entwurf verwerfen.',
  alertPwFocusPublishFirst:
    'Dieser Abschnitt existiert nur im lokalen Entwurf. Bitte zuerst publizieren, dann in ProcessWire öffnen.',
  alertPwFocusUnavailable: 'ProcessWire-Fokus konnte für dieses Ziel nicht vorbereitet werden.',
  openInPw: '→ In PW öffnen',

  /* Field editor / ownership panel */
  ownershipVe: 'Visual Editor',
  ownershipPw: 'ProcessWire',
  infoCardPage: 'Seite',
  infoCardMode: 'Modus',
  infoCardStatus: 'Status',
  infoCardDraft: 'Entwurf',
  modeEditDescription: 'Navigation über die echte Website, Bearbeitung direkt im Layout.',
  modeBrowseDescription: 'Browse: Seite verhält sich wie normale Vorschau.',
  draftOpenDescription: 'Lokaler Entwurf gespeichert und noch nicht publiziert.',
  draftNoneDescription: 'Keine offenen Entwürfe.',
  draftDirtyCount: (count: number) => `${count} Abschnitt(e) ungespeichert. Klicke "Publizieren".`,
  sectionFallbackBadge: 'Abschnitt',
  fieldFallback: 'Feld',
  buttonLabel: (index: number) => `Button ${index + 1}`,
  fieldLabels: {
    title: 'Titel',
    eyebrow: 'Eyebrow',
    text: 'Text',
    media: 'Bild / Medien',
    component: 'Komponente',
    video: 'Video',
    videoTitle: 'Video Titel',
  } as Record<string, string>,
  fieldHintDefaultNoField: 'Klicke ein Feld in der Vorschau an, um inline zu bearbeiten.',
  fieldHints: {
    text: 'Rich Text wird direkt im iframe bearbeitet. Änderungen bleiben lokal, bis du publizierst.',
    media: 'Alt-Text und Medienauswahl laufen über das Overlay direkt im iframe.',
    component: 'Komponentenname wird inline geändert. Komponentenspezifische Optionen sind in V1 noch begrenzt.',
    video: 'Video-URL und Titel können direkt im Overlay bearbeitet werden.',
    videoTitle: 'Video-URL und Titel können direkt im Overlay bearbeitet werden.',
    button: 'Text, Link und Variante werden inline im Button-Overlay geändert.',
  } as Record<string, string>,
  fieldHintDefault: 'Dieses Feld wird direkt in der Vorschau bearbeitet.',

  veRowHero: [
    ['Headline', 'Klicken in der Vorschau'],
    ['Untertitel', 'Klicken in der Vorschau'],
    ['Bild Alt-Text', 'Via Bild-Overlay'],
  ] as ReadonlyArray<readonly [string, string]>,
  veRowTitle: ['Titel', 'Klicken in der Vorschau'] as const,
  veRowEyebrow: ['Eyebrow', 'Klicken in der Vorschau'] as const,
  veRowText: ['Text', 'Klicken → Rich-Text-Editor'] as const,
  veRowLayoutTheme: ['Layout & Thema', 'Via Abschnitt-Overlay'] as const,
  veRowBgOverlay: ['Hintergrundfarbe & Overlay', 'Via Abschnitt-Overlay'] as const,
  veRowButtons: ['Buttons', 'Klicken auf Button → Overlay'] as const,
  veRowMedia: ['Bild (aus Mediathek)', 'Klicken auf Bild → Overlay'] as const,
  veRowMediaMeta: ['Alt-Text, Helligkeit/Kontrast', 'Im Bild-Overlay'] as const,
  veRowVideo: ['Video-URL & Titel', 'Via Video-Overlay'] as const,
  veRowComponentConfig: ['Komponenten-Config', 'Via Komponenten-Overlay'] as const,
  pwRowHeroImage: 'Hero-Bild (Datei)',
  pwRowHeroAll: 'Alle Hero-Felder',
  pwRowImages: 'Bild-Datei(en)',
  pwRowAllFields: 'Alle Felder (Vollansicht)',

  /* Component config editor (sidebar) */
  configEditorHeader: 'Komponenten-Config',

  /* Media modal */
  mediaModalTitle: 'Mediathek',
  btnClose: 'Schliessen',
  mediaLoading: 'Medien werden geladen…',
  mediaEmpty: 'Keine Medien gefunden.',
  mediaLoadFailed: 'Medien konnten nicht geladen werden',
  mediaFallbackName: 'Medium',

  /* Preset modal */
  presetModalTitle: 'Abschnitt-Vorlagen',
  presetSearchPlaceholder: 'Suche...',
  presetAllCategories: 'Alle Kategorien',
  presetLoading: 'Vorlagen werden geladen…',
  presetEmpty: 'Keine Vorlagen gefunden.',
  presetLoadFailed: 'Vorlagen konnten nicht geladen werden',
  presetInsert: 'Einfügen',
  presetFallbackName: 'Vorlage',

  /* Add-section modal */
  addModalTitle: 'Abschnitt hinzufügen',
  addSearchPlaceholder: 'Typ suchen...',
  addAllFilter: 'Alle',
  addEmpty: 'Kein passender Abschnittstyp gefunden.',
  actionAdd: 'Hinzufuegen',
  actionCopy: 'Kopieren',
  actionDelete: 'Löschen',
  actionSort: 'Sortieren',
  actionMove: 'Verschieben',
  actionDuplicate: 'Duplizieren',

  /* Collection panel */
  collectionSuffix: ' · Sammlung (ProcessWire)',
  collectionStatus: (label: string) => `Sammlung: ${label}`,
  collectionDescription: (root: string) =>
    `Diese Einträge liegen als einzelne Seiten unter ${root} und werden direkt in ProcessWire bearbeitet.`,
  collectionEntriesHeader: 'Einträge',
  collectionDateLabel: 'Datum',
  collectionLoading: 'Laden…',
  collectionEmpty: 'Noch keine Einträge. Erstelle den ersten oben.',
  collectionLoadFailed: 'Einträge konnten nicht geladen werden.',
  collectionEntryUntitled: '(ohne Titel)',
  collectionBadgePast: 'Vergangen',
  collectionBadgeUpcoming: 'Bevorstehend',
  busyCreatingEntry: 'Eintrag erstellen…',
  statusEntryCreated: 'Eintrag erstellt — in ProcessWire geöffnet',
  errorEntryCreateFailed: 'Erstellen fehlgeschlagen',

  /* Layout labels + add catalog */
  layoutLabels: {
    hero: 'Hero',
    split_media_text: 'Bild + Text',
    split_text_media: 'Text + Bild',
    full_width_banner: 'Banner',
    media_grid: 'Bildergalerie',
    video_embed: 'Video',
    rich_text: 'Nur Text',
    component: 'Komponente',
  } as Record<string, string>,
  coreLayoutDescriptions: {
    rich_text: 'Einfacher Textblock mit optionalen Buttons.',
    split_media_text: 'Bild links, Text rechts.',
    split_text_media: 'Text links, Bild rechts.',
    full_width_banner: 'Vollbreites Bild mit Text-Overlay.',
    media_grid: 'Mehrspaltige Bildergalerie.',
    video_embed: 'Video-Einbettung (YouTube, Vimeo).',
  } as Record<string, string>,
  addCategoryBase: 'Basis',
  addCategoryOther: 'Sonstiges',
  componentCategories: {
    page_intro: 'Layout', media_text: 'Layout', cards_grid: 'Layout',
    gallery_strip: 'Layout', text_columns: 'Layout', cta_band: 'Layout',
    timeline_header: 'Timeline', timeline_item: 'Timeline',
    contact_form: 'Formulare', membership_form: 'Formulare',
    subscribe_form: 'Formulare', visit_day_form: 'Formulare',
    waiting_list_form: 'Formulare',
    pricing_calculator: 'Interaktiv', events_feed: 'Interaktiv',
    schnuppertage: 'Interaktiv', saisonkalender: 'Interaktiv',
    gallery: 'Interaktiv',
    depot_map: 'Karten', geisshof_map: 'Karten',
  } as Record<string, string>,
  addCategoryOrder: ['Basis', 'Layout', 'Timeline', 'Formulare', 'Interaktiv', 'Karten'] as readonly string[],
} as const

export type ShellStrings = typeof STRINGS
