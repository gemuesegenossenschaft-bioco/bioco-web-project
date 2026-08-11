<?php
/**
 * Seed section -> WP block plan.
 * ============================================================================
 * Turns one seed's `sections` array into an ordered list of "plan items",
 * each describing exactly one bioco/* block to serialize (block dir name +
 * ACF field-group key + field values by ACF field NAME), or a "skip" item
 * for content that cannot be safely represented (reported as a warning, not
 * silently dropped).
 *
 * Mapping precedence mirrors the ProcessWire/Next.js source of truth
 * (SectionRenderer): section_component (a specific interactive block) wins
 * over section_layout (a generic text/media block) when a seed section
 * carries both — this happens for a few sections (e.g. mitmachen.json's
 * "gruppen": layout=rich_text + component=group_cards) where the layout tag
 * is just a legacy fallback alongside the real, structured component.
 *
 * HEADER-BLOCK FALLBACK: some components (bioco/group-cards,
 * bioco/saisonkalender) have NO title/eyebrow/text field of their own (their
 * ACF field groups only carry the component's specific config). If a seed
 * section using one of those components still carries a title/eyebrow/text/
 * buttons (as mitmachen.json's "gruppen" and gemuese.json's "saisonkalender"
 * do), that content would otherwise be silently lost. Instead we emit a
 * plain bioco/rich-text block immediately BEFORE the component block to
 * carry it — this mirrors the original architecture, where every section
 * (generic or component) rendered inside a shared header wrapper. Components
 * that manage their own heading semantics (bioco/events-feed: a "title"
 * field that only applies in banner mode) opt out via 'auto_header' => false
 * and handle it themselves so this fallback never fights their own logic.
 */

if (!defined('ABSPATH')) exit;

// Sentinel marking a value that still needs to be resolved to a WP media
// attachment ID (pages.php does this — it needs $mode/apply context that
// this pure mapping layer intentionally does not have).
function bioco_import_pending_image($url) {
    return ['__bioco_pending_image__' => (string) $url];
}

function bioco_import_is_pending_image($value) {
    return is_array($value) && array_key_exists('__bioco_pending_image__', $value) && count($value) === 1;
}

// Assigns section_eyebrow/section_title/section_text into $values for
// whichever of eyebrow/title/text the target block's ACF group actually
// clones (see $contentClone), and warns (does not drop silently) for any of
// them that is present in the seed but unsupported by the block. Also wires
// the shared buttons repeater when $supportsButtons.
//
// $headerCarriesContent signals that a rich-text header block is being
// prepended for this same section, so eyebrow/title/text are already preserved
// there. Without it this function warns "Inhalt geht sonst verloren" for
// content that IS in fact imported — a false alarm that reads as an instruction
// to re-author the heading by hand, which would produce a duplicate heading.
function bioco_import_apply_common_fields(array &$values, array &$warnings, array $section, array $contentClone, $supportsButtons, $headerCarriesContent = false) {
    $slotToSeedKey = ['eyebrow' => 'section_eyebrow', 'title' => 'section_title', 'text' => 'section_text'];
    foreach ($slotToSeedKey as $slot => $seedKey) {
        $v = isset($section[$seedKey]) ? (string) $section[$seedKey] : '';
        if ($v === '') continue;
        if (in_array($slot, $contentClone, true)) {
            $values[$slot] = $v;
        } elseif (!$headerCarriesContent) {
            $warnings[] = "Feld '{$seedKey}' (\"" . mb_substr($v, 0, 60) . (mb_strlen($v) > 60 ? '…' : '') . "\") wird von diesem Block nicht unterstützt — Inhalt geht sonst verloren.";
        }
    }

    $buttons = isset($section['buttons']) && is_array($section['buttons']) ? array_values($section['buttons']) : [];
    if (!$buttons) return;

    if (!$supportsButtons) {
        // Same false-alarm rule as above: the prepended header block is built
        // with buttons enabled, so they are preserved there.
        if (!$headerCarriesContent) {
            $warnings[] = 'Buttons im Seed vorhanden, aber dieser Block unterstützt keine Buttons — Inhalt geht sonst verloren.';
        }
        return;
    }
    $rows = [];
    foreach ($buttons as $button) {
        if (!is_array($button)) continue;
        $row = [];
        if (!empty($button['text'])) $row['text'] = (string) $button['text'];
        if (!empty($button['href'])) $row['href'] = (string) $button['href'];
        $row['variant'] = (string) ($button['variant'] ?? 'primary');
        $rows[] = $row;
    }
    if ($rows) $values['buttons'] = $rows;
}

// Reads section_config[$key] with a typed default; never fatals on a
// missing/non-array section_config.
function bioco_import_config_value(array $section, $key, $default = '') {
    $config = is_array($section['section_config'] ?? null) ? $section['section_config'] : [];
    return array_key_exists($key, $config) && $config[$key] !== '' ? $config[$key] : $default;
}

function bioco_import_media_text_layout_config($mediaSide) {
    return [
        'block' => 'media-text',
        'acf_group' => 'group_bioco_block_media_text',
        'content_clone' => ['eyebrow', 'title', 'text'],
        'buttons' => true,
        'auto_header' => true,
        'extra' => function (array $section, array &$values, array &$warnings) use ($mediaSide) {
            $values['media_side'] = $mediaSide;
            $url = (string) ($section['image_url'] ?? '');
            $alt = (string) ($section['image_alt'] ?? '');
            if ($url !== '') $values['image'] = bioco_import_pending_image($url);
            if ($alt !== '') $values['image_alt'] = $alt;
        },
    ];
}

function bioco_import_layout_map() {
    return [
        'rich_text' => [
            'block' => 'rich-text',
            'acf_group' => 'group_bioco_block_rich_text',
            'content_clone' => ['eyebrow', 'title', 'text'],
            'buttons' => true,
            'auto_header' => true,
        ],
        'split_media_text' => bioco_import_media_text_layout_config('left'),
        'split_text_media' => bioco_import_media_text_layout_config('right'),
        'full_width_banner' => [
            'block' => 'banner',
            'acf_group' => 'group_bioco_block_banner',
            'content_clone' => ['eyebrow', 'title', 'text'],
            'buttons' => true,
            'auto_header' => true,
            'extra' => function (array $section, array &$values, array &$warnings) {
                $url = (string) ($section['image_url'] ?? '');
                $alt = (string) ($section['image_alt'] ?? '');
                if ($url !== '') $values['image'] = bioco_import_pending_image($url);
                if ($alt !== '') $values['image_alt'] = $alt;
            },
        ],
        'media_grid' => [
            'block' => 'media-grid',
            'acf_group' => 'group_bioco_block_media_grid',
            'content_clone' => ['eyebrow', 'title', 'text'],
            'buttons' => true,
            'auto_header' => true,
        ],
        // 'video_embed' intentionally absent: field_bioco_video_embed_video_url
        // is required and has no seed-schema counterpart (see README.md — the
        // 17 seeds only ever use rich_text/split_media_text/split_text_media).
        // Handled as an explicit skip in bioco_import_plan_single_section().
    ];
}

function bioco_import_component_map() {
    return [
        'contact_form' => [
            'block' => 'contact-form',
            'acf_group' => 'group_bioco_block_contact_form',
            'content_clone' => ['title', 'text'],
            'buttons' => false,
            'auto_header' => true,
        ],
        'membership_form' => [
            'block' => 'membership-form',
            'acf_group' => 'group_bioco_block_membership_form',
            'content_clone' => ['title', 'text'],
            'buttons' => false,
            'auto_header' => true,
        ],
        'subscribe_form' => [
            'block' => 'subscribe-form',
            'acf_group' => 'group_bioco_block_subscribe_form',
            'content_clone' => ['title', 'text'],
            'buttons' => false,
            'auto_header' => true,
        ],
        'visit_day_form' => [
            'block' => 'visit-day-form',
            'acf_group' => 'group_bioco_block_visit_day_form',
            'content_clone' => ['title', 'text'],
            'buttons' => false,
            'auto_header' => true,
        ],
        'waiting_list_form' => [
            'block' => 'waiting-list-form',
            'acf_group' => 'group_bioco_block_waiting_list_form',
            'content_clone' => ['title', 'text'],
            'buttons' => false,
            'auto_header' => true,
        ],
        'pricing_calculator' => [
            'block' => 'pricing-calculator',
            'acf_group' => 'group_bioco_block_pricing_calculator',
            'content_clone' => ['eyebrow', 'title', 'text'],
            'buttons' => false,
            'auto_header' => true,
        ],
        'steps' => [
            'block' => 'steps',
            'acf_group' => 'group_bioco_block_steps',
            'content_clone' => ['title'],
            'buttons' => false,
            'auto_header' => true,
            'extra' => function (array $section, array &$values, array &$warnings) {
                $items = [];
                for ($n = 1; $n <= 4; $n++) {
                    $title = (string) bioco_import_config_value($section, "step{$n}_title");
                    $text = (string) bioco_import_config_value($section, "step{$n}_text");
                    if ($title === '' && $text === '') continue;
                    $row = [];
                    if ($title !== '') $row['title'] = $title;
                    if ($text !== '') $row['text'] = $text;
                    $items[] = $row;
                }
                if ($items) $values['items'] = $items;
            },
        ],
        'link_tiles' => [
            'block' => 'link-tiles',
            'acf_group' => 'group_bioco_block_link_tiles',
            'content_clone' => ['title'],
            'buttons' => false,
            'auto_header' => true,
            'extra' => function (array $section, array &$values, array &$warnings) {
                $tiles = [];
                for ($n = 1; $n <= 4; $n++) {
                    $title = (string) bioco_import_config_value($section, "tile{$n}_title");
                    if ($title === '') continue;
                    $row = ['title' => $title];
                    $text = (string) bioco_import_config_value($section, "tile{$n}_text");
                    $href = (string) bioco_import_config_value($section, "tile{$n}_href");
                    $icon = (string) bioco_import_config_value($section, "tile{$n}_icon");
                    if ($text !== '') $row['text'] = $text;
                    if ($href !== '') $row['href'] = $href;
                    if ($icon !== '') $row['icon'] = $icon;
                    $tiles[] = $row;
                }
                if ($tiles) $values['tiles'] = $tiles;
            },
        ],
        'schnuppertage' => [
            'block' => 'schnuppertage',
            'acf_group' => 'group_bioco_block_schnuppertage',
            'content_clone' => ['title', 'text'],
            'buttons' => false,
            'auto_header' => true,
            'extra' => function (array $section, array &$values, array &$warnings) {
                $listLabel = (string) bioco_import_config_value($section, 'list_label');
                if ($listLabel !== '') $values['list_label'] = $listLabel;
                $closing = (string) bioco_import_config_value($section, 'closing');
                if ($closing !== '') $values['closing'] = $closing;
                $items = [];
                for ($n = 1; $n <= 7; $n++) {
                    $text = (string) bioco_import_config_value($section, "item{$n}");
                    if ($text === '') continue;
                    $items[] = ['text' => $text];
                }
                if ($items) $values['items'] = $items;
            },
        ],
        'group_cards' => [
            'block' => 'group-cards',
            'acf_group' => 'group_bioco_block_group_cards',
            'content_clone' => [],
            'buttons' => false,
            'auto_header' => true,
            // 'limit' is not part of the seed schema — leave unset, the ACF
            // default_value (-1 = all groups) applies at render.
        ],
        'saisonkalender' => [
            'block' => 'saisonkalender',
            'acf_group' => 'group_bioco_block_saisonkalender',
            'content_clone' => [],
            'buttons' => false,
            'auto_header' => true,
            // 'vegetables' is not part of the seed schema — leave unset, the
            // render.php example list applies (see its own instructions).
        ],
        'depot_map' => [
            'block' => 'depot-map',
            'acf_group' => 'group_bioco_block_depot_map',
            'content_clone' => [],
            'buttons' => false,
            'auto_header' => true,
            // 'intro'/'locations' not part of the seed schema — leave unset,
            // render.php's nine-depot fallback list applies.
        ],
        'geisshof_map' => [
            'block' => 'geisshof-map',
            'acf_group' => 'group_bioco_block_geisshof_map',
            'content_clone' => [],
            'buttons' => false,
            'auto_header' => true,
            // 'locations' not part of the seed schema — leave unset, the
            // single default Geisshof location applies.
        ],
        'events_feed' => [
            'block' => 'events-feed',
            'acf_group' => 'group_bioco_block_events_feed',
            'content_clone' => [],
            'buttons' => false,
            'auto_header' => false, // handles section_title itself, see 'extra'
            'skip_common' => true,
            'extra' => function (array $section, array &$values, array &$warnings) {
                $variant = (string) bioco_import_config_value($section, 'variant');
                if ($variant !== '') $values['variant'] = $variant;
                $limit = bioco_import_config_value($section, 'limit', null);
                if ($limit !== null && $limit !== '') $values['limit'] = (int) $limit;

                $title = (string) ($section['section_title'] ?? '');
                if ($title !== '') {
                    if ($variant === 'banner') {
                        $values['title'] = $title;
                    } else {
                        // Not a loss: the live Next.js site ignores this seed
                        // field here too — EventsSection.tsx hardcodes
                        // <h3>Nächste Events</h3> and never reads section_title
                        // (pinned by frontend/tests/cms-pages-parity.test.ts).
                        // So dropping it IS parity. Said explicitly, because
                        // "fixing" this on staging would CHANGE the site.
                        $warnings[] = "Hinweis (kein Inhaltsverlust): section_title \"{$title}\" wird bewusst nicht übernommen. Die bestehende Website ignoriert dieses Feld an dieser Stelle ebenfalls — EventsSection.tsx zeigt fest „Nächste Events“. bioco/events-feed hat ein Titel-Feld nur im 'banner'-Modus. Bitte NICHT nachträglich ergänzen, das wäre eine Änderung gegenüber der aktuellen Website.";
                    }
                }
                foreach (['section_eyebrow' => 'section_eyebrow', 'section_text' => 'section_text'] as $seedKey => $label) {
                    if (!empty($section[$seedKey])) {
                        $warnings[] = "{$label} im Seed vorhanden, aber bioco/events-feed unterstützt kein {$label}-Feld — Inhalt geht sonst verloren.";
                    }
                }
                if (!empty($section['buttons'])) {
                    $warnings[] = 'Buttons im Seed vorhanden, aber bioco/events-feed unterstützt keine Buttons — Inhalt geht sonst verloren.';
                }
            },
        ],
    ];
}

// Builds one bioco/accordion block from a run of consecutive
// section_component=accordion_item sections (see file docblock).
function bioco_import_plan_accordion_group(array $sectionGroup) {
    $items = [];
    $ids = [];
    foreach ($sectionGroup as $section) {
        $ids[] = (string) $section['section_id'];
        $row = [];
        if (!empty($section['section_title'])) $row['title'] = (string) $section['section_title'];
        if (!empty($section['section_text'])) $row['body'] = (string) $section['section_text'];
        $items[] = $row;
    }
    return [[
        'type' => 'block',
        'section_ids' => $ids,
        'block' => 'accordion',
        'acf_group' => 'group_bioco_block_accordion',
        'values' => ['items' => $items],
    ]];
}

// Maps a single (non-accordion) seed section to 0-2 plan items (1 normally,
// 2 when the header-block fallback fires, 0 only in truly unrepresentable
// cases such as video_embed — which still reports via 'skip').
function bioco_import_plan_single_section(array $section) {
    $sid = (string) $section['section_id'];
    $warnings = [];
    $componentKey = (string) ($section['section_component'] ?? '');
    $layoutKey = (string) ($section['section_layout'] ?? '');

    $config = null;
    if ($componentKey !== '') {
        $map = bioco_import_component_map();
        if (isset($map[$componentKey])) {
            $config = $map[$componentKey];
        } else {
            return [['type' => 'skip', 'section_ids' => [$sid], 'reason' => "Unbekannte Komponente '{$componentKey}' — kein bioco/*-Block dafür registriert. Section manuell in Divi/Block-Editor nachbilden."]];
        }
    } elseif ($layoutKey === 'video_embed') {
        return [['type' => 'skip', 'section_ids' => [$sid], 'reason' => "section_layout 'video_embed' benötigt video_url (ACF-Pflichtfeld field_bioco_video_embed_video_url), das im Seed-Schema nicht existiert — Block manuell in Divi/Block-Editor einfügen."]];
    } elseif ($layoutKey !== '') {
        $map = bioco_import_layout_map();
        if (isset($map[$layoutKey])) {
            $config = $map[$layoutKey];
        } else {
            return [['type' => 'skip', 'section_ids' => [$sid], 'reason' => "Unbekannter section_layout-Wert '{$layoutKey}'. Section manuell in Divi/Block-Editor nachbilden."]];
        }
    } else {
        return [['type' => 'skip', 'section_ids' => [$sid], 'reason' => 'Section hat weder section_layout noch section_component — nichts zu importieren.']];
    }

    $values = [];
    $contentClone = $config['content_clone'];
    $supportsButtons = !empty($config['buttons']);

    // Decided BEFORE applying the common fields, because the answer changes
    // whether an unsupported eyebrow/title/text is a real loss (warn) or is
    // simply carried by the prepended header block (stay quiet).
    $needsHeader = !empty($config['auto_header']) && empty($contentClone)
        && (!empty($section['section_eyebrow']) || !empty($section['section_title']) || !empty($section['section_text']) || !empty($section['buttons']));

    if (empty($config['skip_common'])) {
        bioco_import_apply_common_fields($values, $warnings, $section, $contentClone, $supportsButtons, $needsHeader);
    }
    if (isset($config['extra'])) {
        $config['extra']($section, $values, $warnings);
    }

    $items = [];

    if ($needsHeader) {
        $headerValues = [];
        $headerWarnings = [];
        bioco_import_apply_common_fields($headerValues, $headerWarnings, $section, ['eyebrow', 'title', 'text'], true);
        $items[] = [
            'type' => 'block',
            'section_ids' => [$sid],
            'block' => 'rich-text',
            'acf_group' => 'group_bioco_block_rich_text',
            'values' => $headerValues,
            'warnings' => $headerWarnings,
            'note' => 'Automatisch vorangestellter Titel-/Text-Block (die Komponente selbst hat kein Titel-/Textfeld).',
        ];
    }

    $items[] = [
        'type' => 'block',
        'section_ids' => [$sid],
        'block' => $config['block'],
        'acf_group' => $config['acf_group'],
        'values' => $values,
        'warnings' => $warnings,
    ];

    return $items;
}

// Full seed -> ordered plan (flattens accordion grouping + header fallback).
function bioco_import_build_page_plan(array $seed) {
    $sections = $seed['sections'];
    $plan = [];
    $i = 0;
    $n = count($sections);
    while ($i < $n) {
        $componentKey = (string) ($sections[$i]['section_component'] ?? '');
        if ($componentKey === 'accordion_item') {
            $group = [];
            while ($i < $n && (string) ($sections[$i]['section_component'] ?? '') === 'accordion_item') {
                $group[] = $sections[$i];
                $i++;
            }
            foreach (bioco_import_plan_accordion_group($group) as $item) $plan[] = $item;
            continue;
        }
        foreach (bioco_import_plan_single_section($sections[$i]) as $item) $plan[] = $item;
        $i++;
    }
    return $plan;
}
