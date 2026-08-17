<?php
/**
 * Native Divi section composer.
 *
 * Converts resolved plan items into native Divi 5 section/row/column/module
 * trees. One public entry point; all primitive builders stay private.
 */

if (!defined('ABSPATH')) exit;

final class Bioco_Import_Divi_Composer {

    /**
     * Build a native Divi section tree from one resolved plan item.
     *
     * Supported blocks: hero, page-intro, media-text, rich-text, cta-band,
     * text-columns, steps, link-tiles, cards-grid, gallery-strip, timeline,
     * pricing-table, accordion, homepage chrome, and registered dynamic components.
     *
     * @param array $item Resolved plan item with keys 'block' and 'values'.
     * @return array WordPress block array (divi/section root).
     * @throws InvalidArgumentException
     */
    public static function section(array $item): array {
        $block = $item['block'] ?? '';
        $values = is_array($item['values'] ?? null) ? $item['values'] : [];

        $dynamicComponents = [];
        if (function_exists('bioco_dynamic_components')) {
            foreach (bioco_dynamic_components() as $componentKey => $blockName) {
                $dynamicComponents[substr($blockName, strpos($blockName, '/') + 1)] = $componentKey;
            }
        }
        if (isset($dynamicComponents[$block])) {
            return self::dynamicSection($dynamicComponents[$block], $values);
        }

        switch ($block) {
            case 'hero':
                return self::heroSection($values);
            case 'page-intro':
                return self::pageIntroSection($values);
            case 'media-text':
                return ($values['style_variant'] ?? '') === 'feature'
                    ? self::homeMediaTextSection($values)
                    : self::mediaTextSection($values);
            case 'rich-text':
                return ($values['style_variant'] ?? '') === 'feature'
                    ? self::homeRichTextSection($values)
                    : self::richTextSection($values);
            case 'cta-band':
                return self::ctaBandSection($values);
            case 'text-columns':
                return self::textColumnsSection($values);
            case 'steps':
                return self::stepsSection($values);
            case 'link-tiles':
                return self::linkTilesSection($values);
            case 'cards-grid':
                return self::cardsGridSection($values);
            case 'gallery-strip':
                return self::galleryStripSection($values);
            case 'timeline':
                return self::timelineSection($values);
            case 'pricing-table':
                return self::pricingTableSection($values);
            case 'accordion':
                return self::accordionSection($values);
            case 'home-chrome':
                return self::homeChromeSection();
            default:
                throw new InvalidArgumentException("Unsupported Divi section block: {$block}");
        }
    }

    private static function dynamicSection(string $componentKey, array $values): array {
        $sectionHeading = trim((string) ($values['_section_heading'] ?? ''));
        unset($values['_section_heading']);
        $children = [];
        if ($sectionHeading !== '') {
            $children[] = self::headingBlock($sectionHeading, 'h2', 'bioco-dynamic-section-title');
        }
        $children[] = self::textBlock(
            bioco_dynamic_marker_html($componentKey, $values),
            'bioco-dynamic-text'
        );

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-dynamic-section')),
            [self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-dynamic-row')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-dynamic-column')),
                    $children
                )]
            )]
        );
    }

    private static function heroSection(array $values): array {
        $children = [];

        $img = self::imageBlock(
            isset($values['image']) ? (int) $values['image'] : null,
            (string) ($values['image_alt'] ?? ''),
            'bioco-home-hero-image'
        );
        if ($img) {
            $children[] = $img;
        }

        $headline = (string) ($values['headline'] ?? '');
        $subtitle = (string) ($values['subtitle'] ?? '');
        $subtitleHtml = $subtitle !== '' ? str_replace(["\r\n", "\r", "\n"], '<br>', $subtitle) : '';
        $subtitleHtml = $subtitleHtml !== '' ? "<p>{$subtitleHtml}</p>" : '';

        $children[] = self::headingBlock($headline, 'h1', 'bioco-home-hero-title');
        $children[] = self::textBlock($subtitleHtml, 'bioco-home-hero-subtitle');

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-home-hero')),
            [self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-home-hero-row')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-home-hero-column')),
                    $children
                )]
            )]
        );
    }

    private static function homeMediaTextSection(array $values): array {
        $side = $values['media_side'] ?? 'left';

        $mediaImg = self::imageBlock(
            isset($values['image']) ? (int) $values['image'] : null,
            (string) ($values['image_alt'] ?? ''),
            'bioco-home-feature-image'
        );
        $mediaColumnChildren = $mediaImg ? [$mediaImg] : [];
        $mediaColumn = self::withChildren(
            bioco_import_divi_block('divi/column', self::columnAttr('1_2', 'bioco-home-feature-media')),
            $mediaColumnChildren
        );

        $contentChildren = [
            self::headingBlock((string) ($values['title'] ?? ''), 'h2', 'bioco-home-feature-title'),
            self::textBlock((string) ($values['text'] ?? ''), 'bioco-home-feature-text'),
        ];
        foreach ($values['buttons'] ?? [] as $btn) {
            if (is_array($btn)) {
                $contentChildren[] = self::homeButtonBlock($btn);
            }
        }
        $contentColumn = self::withChildren(
            bioco_import_divi_block('divi/column', self::columnAttr('1_2', 'bioco-home-feature-content')),
            $contentChildren
        );

        $columns = $side === 'right'
            ? [$contentColumn, $mediaColumn]
            : [$mediaColumn, $contentColumn];

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-home-feature')),
            [self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('1_2,1_2', 'bioco-home-feature-row')),
                $columns
            )]
        );
    }

    private static function homeRichTextSection(array $values): array {
        $children = [
            self::headingBlock((string) ($values['title'] ?? ''), 'h2', 'bioco-home-cta-title'),
            self::textBlock((string) ($values['text'] ?? ''), 'bioco-home-cta-text'),
        ];
        foreach ($values['buttons'] ?? [] as $btn) {
            if (is_array($btn)) {
                $children[] = self::homeButtonBlock($btn);
            }
        }

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-home-cta')),
            [self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-home-cta-content')),
                    $children
                )]
            )]
        );
    }

    private static function homeChromeSection(): array {
        $events = bioco_dynamic_marker_html('events_feed', [
            'variant' => 'banner',
            'title' => 'Kommende Events',
            'banner_link_label' => 'Alle Events ansehen',
            'archive_url' => '/aktuelles',
            'empty_message' => 'Aktuell sind keine kommenden Events geplant.',
            'limit' => 3,
            'respect_stored_status' => true,
        ]);
        $visits = bioco_dynamic_marker_html('schnuppertage', [
            'title' => 'Schnuppertage',
            'empty_message' => 'Aktuell sind keine Schnuppertage geplant.',
            'limit' => 3,
            'respect_stored_status' => true,
        ]);

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-home-live')),
            [self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-home-live-row')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-home-live-column')),
                    [
                        self::headingBlock('Beiträge', 'h2', 'bioco-home-live-title'),
                        self::homeButtonBlock([
                            'text' => 'Alle Beiträge ansehen',
                            'href' => '/aktuelles',
                            'variant' => 'secondary',
                        ]),
                        self::textBlock($events, 'bioco-home-live-events'),
                        self::textBlock($visits, 'bioco-home-live-visits'),
                    ]
                )]
            )]
        );
    }

    private static function pageIntroSection(array $values): array {
        $container = self::modifier($values['container_width'] ?? '', ['sm', 'md', 'lg', 'xl'], 'lg');
        $textWidth = self::modifier($values['text_width'] ?? '', ['normal', 'wide'], 'normal');
        $align = self::modifier($values['align'] ?? '', ['left', 'center'], 'left');
        $headingLevel = (string) ($values['heading_level'] ?? '') === '1' ? 'h1' : 'h2';

        return self::singleColumnSection(
            "bioco-divi-section bioco-divi-page-intro bioco-divi-width-{$container} bioco-divi-align-{$align}",
            "bioco-divi-content bioco-divi-text-{$textWidth}",
            self::contentBlocks($values, $headingLevel)
        );
    }

    private static function mediaTextSection(array $values): array {
        $side = self::modifier($values['media_side'] ?? '', ['left', 'right'], 'left');
        $contentColumn = self::withChildren(
            bioco_import_divi_block('divi/column', self::columnAttr('1_2', 'bioco-divi-content')),
            self::contentBlocks($values)
        );
        $image = self::imageBlock(
            isset($values['image']) ? (int) $values['image'] : null,
            (string) ($values['image_alt'] ?? ''),
            'bioco-divi-media-image'
        );

        if (!$image) {
            $contentColumn['attrs'] = self::columnAttr('4_4', 'bioco-divi-content');
            $row = self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-divi-row')),
                [$contentColumn]
            );
        } else {
            $mediaColumn = self::withChildren(
                bioco_import_divi_block('divi/column', self::columnAttr('1_2', 'bioco-divi-media')),
                [$image]
            );
            $row = self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('1_2,1_2', 'bioco-divi-row')),
                [$mediaColumn, $contentColumn]
            );
        }

        return self::withChildren(
            bioco_import_divi_block(
                'divi/section',
                self::classAttr("bioco-divi-section bioco-divi-media-text bioco-divi-media-{$side}")
            ),
            [$row]
        );
    }

    private static function richTextSection(array $values): array {
        return self::singleColumnSection(
            'bioco-divi-section bioco-divi-rich-text',
            'bioco-divi-content',
            self::contentBlocks($values)
        );
    }

    private static function ctaBandSection(array $values): array {
        $align = self::modifier($values['align'] ?? '', ['left', 'center'], 'left');
        $theme = self::modifier($values['theme'] ?? '', ['soft', 'light', 'dark'], 'soft');
        $rounded = self::modifier($values['rounded'] ?? '', ['none', 'sm', 'lg', 'xl'], 'xl');

        return self::singleColumnSection(
            "bioco-divi-section bioco-divi-cta bioco-divi-align-{$align} bioco-divi-theme-{$theme} bioco-divi-rounded-{$rounded}",
            'bioco-divi-content',
            self::contentBlocks($values)
        );
    }

    private static function textColumnsSection(array $values): array {
        $container = self::modifier($values['container_width'] ?? '', ['sm', 'md', 'lg', 'xl'], 'lg');
        $columns   = self::modifier($values['columns'] ?? '', ['1', '2', '3', '4'], '2');
        $gap       = self::modifier($values['gap'] ?? '', ['sm', 'md', 'lg', 'xl'], 'lg');

        $children = [];

        $eyebrow = trim((string) ($values['eyebrow'] ?? ''));
        if ($eyebrow !== '') {
            $children[] = self::textBlock($eyebrow, 'bioco-divi-eyebrow');
        }

        $title = trim((string) ($values['title'] ?? ''));
        $text  = (string) ($values['text'] ?? '');
        if ($title !== '' && !self::textHasHeading($text)) {
            $children[] = self::headingBlock($title, 'h2', 'bioco-divi-title');
        }

        if ($text !== '') {
            $children[] = self::textBlock(
                $text,
                "bioco-divi-text bioco-divi-text-columns-body bioco-divi-columns-{$columns} bioco-divi-gap-{$gap}"
            );
        }

        $image = self::imageBlock(
            isset($values['image']) ? (int) $values['image'] : null,
            (string) ($values['image_alt'] ?? ''),
            'bioco-divi-text-columns-image'
        );
        if ($image) {
            $children[] = $image;
        }

        foreach ($values['buttons'] ?? [] as $button) {
            if (!is_array($button)) continue;
            $block = self::buttonBlock($button);
            if ($block) $children[] = $block;
        }

        return self::singleColumnSection(
            "bioco-divi-section bioco-divi-text-columns bioco-divi-width-{$container}",
            'bioco-divi-content',
            $children
        );
    }

    private static function stepsSection(array $values): array {
        $children = [];

        $title = trim((string) ($values['title'] ?? ''));
        if ($title !== '') {
            $children[] = self::headingBlock($title, 'h2', 'bioco-divi-steps-title');
        }

        $steps = [];
        foreach ($values['items'] ?? [] as $item) {
            if (!is_array($item)) continue;
            $stepTitle = trim((string) ($item['title'] ?? ''));
            $stepText  = trim((string) ($item['text'] ?? ''));
            if ($stepTitle === '' && $stepText === '') continue;
            $steps[] = ['title' => $stepTitle, 'text' => $stepText];
        }

        if ($steps) {
            $html = '<div class="next-steps">';
            foreach ($steps as $index => $step) {
                $number = (string) ($index + 1);
                $html .= '<div class="step-item">';
                $html .= '<div class="step-number">' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . '</div>';
                $html .= '<div>';
                if ($step['title'] !== '') {
                    $html .= '<h3>' . htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
                }
                if ($step['text'] !== '') {
                    $html .= '<p>' . htmlspecialchars($step['text'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                $html .= '</div></div>';
            }
            $html .= '</div>';
            $children[] = self::textBlock($html, 'bioco-divi-steps-body');
        }

        return self::singleColumnSection(
            'bioco-divi-section bioco-divi-steps',
            'bioco-divi-content',
            $children
        );
    }

    private static function linkTilesSection(array $values): array {
        $children = [];

        $title = trim((string) ($values['title'] ?? ''));
        if ($title !== '') {
            $children[] = self::headingBlock($title, 'h2', 'bioco-divi-link-tiles-title');
        }

        $tiles = [];
        foreach ($values['tiles'] ?? [] as $tile) {
            if (!is_array($tile)) continue;
            $tileTitle = trim((string) ($tile['title'] ?? ''));
            if ($tileTitle === '') continue;
            $tiles[] = [
                'title' => $tileTitle,
                'text'  => trim((string) ($tile['text'] ?? '')),
                'href'  => trim((string) ($tile['href'] ?? '')),
                'icon'  => trim((string) ($tile['icon'] ?? '')),
            ];
        }

        if ($tiles) {
            $html = '<div class="portal-gateway">';
            foreach ($tiles as $tile) {
                $tagAttrs = self::linkTileTagAttrs($tile['href']);
                $tag  = $tagAttrs['tag'];
                $attrs = $tagAttrs['attrs'];
                $html .= '<' . $tag . ' ' . $attrs . '>';
                if ($tile['icon'] !== '') {
                    $html .= '<div class="portal-icon">' . htmlspecialchars($tile['icon'], ENT_QUOTES, 'UTF-8') . '</div>';
                }
                $html .= '<h3>' . htmlspecialchars($tile['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
                if ($tile['text'] !== '') {
                    $html .= '<p>' . htmlspecialchars($tile['text'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                $html .= '</' . $tag . '>';
            }
            $html .= '</div>';
            $children[] = self::textBlock($html, 'bioco-divi-link-tiles-body');
        }

        return self::singleColumnSection(
            'bioco-divi-section bioco-divi-link-tiles',
            'bioco-divi-content',
            $children
        );
    }

    private static function singleColumnSection(string $sectionClass, string $columnClass, array $children): array {
        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr($sectionClass)),
            [self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-divi-row')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', $columnClass)),
                    $children
                )]
            )]
        );
    }

    private static function headerBlocks(array $values, string $headingLevel = 'h2'): array {
        $children = [];
        $eyebrow = trim((string) ($values['eyebrow'] ?? ''));
        $title = trim((string) ($values['title'] ?? ''));
        $text = (string) ($values['text'] ?? '');

        if ($eyebrow !== '') {
            $children[] = self::textBlock($eyebrow, 'bioco-divi-eyebrow');
        }
        if ($title !== '' && !self::textHasHeading($text)) {
            $children[] = self::headingBlock($title, $headingLevel, 'bioco-divi-title');
        }
        if ($text !== '') {
            $children[] = self::textBlock($text, 'bioco-divi-text');
        }
        return $children;
    }

    private static function contentBlocks(array $values, string $headingLevel = 'h2'): array {
        $children = self::headerBlocks($values, $headingLevel);
        foreach ($values['buttons'] ?? [] as $button) {
            if (!is_array($button)) continue;
            $block = self::buttonBlock($button);
            if ($block) $children[] = $block;
        }
        return $children;
    }

    private static function modifier($value, array $allowed, string $fallback): string {
        $value = (string) $value;
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function textHasHeading(string $html): bool {
        return preg_match('/<h[1-6]\b/i', $html) === 1;
    }

    private static function classAttr(string $class): array {
        return [
            'module' => [
                'advanced' => [
                    'htmlAttributes' => [
                        'desktop' => [
                            'value' => ['class' => $class],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function rowAttr(string $structure, ?string $class = null): array {
        $attr = [
            'module' => [
                'advanced' => [
                    'columnStructure' => [
                        'desktop' => ['value' => $structure],
                    ],
                ],
            ],
        ];
        if ($class !== null) {
            $attr['module']['advanced']['htmlAttributes'] = [
                'desktop' => ['value' => ['class' => $class]],
            ];
        }
        return $attr;
    }

    private static function columnAttr(string $type, string $class): array {
        return [
            'module' => [
                'advanced' => [
                    'type' => [
                        'desktop' => ['value' => $type],
                    ],
                    'htmlAttributes' => [
                        'desktop' => [
                            'value' => ['class' => $class],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function headingBlock(string $text, string $level, string $class): array {
        $escaped = str_replace(["\r\n", "\r", "\n"], '<br>', $text);
        return bioco_import_divi_block('divi/heading', [
            'title' => [
                'innerContent' => [
                    'desktop' => ['value' => $escaped],
                ],
                'decoration' => [
                    'font' => [
                        'font' => [
                            'desktop' => [
                                'value' => ['headingLevel' => $level],
                            ],
                        ],
                    ],
                ],
            ],
        ] + self::classAttr($class));
    }

    private static function textBlock(string $html, string $class): array {
        return bioco_import_divi_block('divi/text', [
            'content' => [
                'innerContent' => [
                    'desktop' => ['value' => $html],
                ],
            ],
        ] + self::classAttr($class));
    }

    private static function imageBlock(?int $attachmentId, string $alt, string $class): ?array {
        if (!$attachmentId) {
            return null;
        }
        $url = wp_get_attachment_image_url($attachmentId, 'full');
        if (!$url) {
            return null;
        }
        return bioco_import_divi_block('divi/image', [
            'image' => [
                'innerContent' => [
                    'desktop' => [
                        'value' => [
                            'src' => $url,
                            'id'  => $attachmentId,
                            'alt' => $alt,
                        ],
                    ],
                ],
            ],
        ] + self::classAttr($class));
    }

    private static function homeButtonBlock(array $btn): array {
        $variant = $btn['variant'] ?? 'primary';
        if (!in_array($variant, ['primary', 'secondary'], true)) {
            $variant = 'primary';
        }
        return bioco_import_divi_block('divi/button', [
            'button' => [
                'innerContent' => [
                    'desktop' => [
                        'value' => [
                            'text'       => $btn['text'] ?? '',
                            'linkUrl'    => $btn['href'] ?? '',
                            'linkTarget' => 'off',
                        ],
                    ],
                ],
            ],
        ] + self::classAttr("bioco-home-button bioco-home-button--{$variant}"));
    }

    private static function buttonBlock(array $button): ?array {
        $text = trim((string) ($button['text'] ?? ''));
        $href = trim((string) ($button['href'] ?? ''));
        if ($text === '' || $href === '') return null;

        $variant = self::modifier($button['variant'] ?? '', ['primary', 'secondary'], 'primary');
        $external = function_exists('bioco_link_is_external')
            ? bioco_link_is_external($href)
            : preg_match('#^https?://#i', $href) === 1;

        return bioco_import_divi_block('divi/button', [
            'button' => [
                'innerContent' => [
                    'desktop' => [
                        'value' => [
                            'text'       => $text,
                            'linkUrl'    => $href,
                            'linkTarget' => $external ? 'on' : 'off',
                        ],
                    ],
                ],
            ],
        ] + self::classAttr("bioco-divi-button bioco-divi-button--{$variant}"));
    }

    private static function hrefKind(string $href): string {
        $href = trim($href);
        if ($href === '') return 'unsafe';
        if ($href[0] === '/') return 'relative';
        if (preg_match('#^https?://#i', $href) === 1) return 'external';
        return 'unsafe';
    }

    private static function linkTileTagAttrs(string $href): array {
        $kind = self::hrefKind($href);

        if ($kind === 'unsafe') {
            return ['tag' => 'div', 'attrs' => 'class="portal-tile"'];
        }

        $attrs = 'class="portal-tile" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        if ($kind === 'external') {
            $attrs .= ' target="_blank" rel="noopener noreferrer"';
        }
        return ['tag' => 'a', 'attrs' => $attrs];
    }

    private static function timelineSection(array $values): array {
        $container  = self::modifier($values['container_width'] ?? '', ['md', 'lg', 'xl'], 'lg');
        $textWidth  = self::modifier($values['text_width']    ?? '', ['narrow', 'normal', 'wide'], 'normal');
        $align      = self::modifier($values['align']          ?? '', ['left', 'center'], 'left');

        $rows = [];
        $headerChildren = self::headerBlocks($values);
        if ($headerChildren) {
            $rows[] = self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-divi-row bioco-divi-timeline-header')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', "bioco-divi-content bioco-divi-text-{$textWidth} bioco-divi-align-{$align}")),
                    $headerChildren
                )]
            );
        }

        foreach ($values['items'] ?? [] as $item) {
            if (!is_array($item)) continue;
            $year  = trim((string) ($item['year_eyebrow'] ?? ''));
            $title = trim((string) ($item['title']        ?? ''));
            $text  = trim((string) ($item['text']         ?? ''));
            if ($year === '' && $title === '' && $text === '') continue;

            $emphasis = self::modifier($item['emphasis'] ?? '', ['normal', 'highlight'], 'normal');
            $badgeText = $year !== '' ? $year : '•';

            $contentChildren = [];
            if ($title !== '') {
                $contentChildren[] = self::headingBlock($title, 'h3', 'bioco-divi-timeline-item-title');
            }
            if ($text !== '') {
                $contentChildren[] = self::textBlock($text, 'bioco-divi-timeline-item-text');
            }
            if (!$contentChildren) continue;

            $rows[] = self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('1_4,3_4', "bioco-divi-row bioco-divi-timeline-item-row bioco-divi-timeline-item--{$emphasis}")),
                [
                    self::withChildren(
                        bioco_import_divi_block('divi/column', self::columnAttr('1_4', 'bioco-divi-timeline-badge-col')),
                        [self::textBlock($badgeText, 'bioco-divi-timeline-badge')]
                    ),
                    self::withChildren(
                        bioco_import_divi_block('divi/column', self::columnAttr('3_4', 'bioco-divi-timeline-item-content')),
                        $contentChildren
                    ),
                ]
            );
        }

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr("bioco-divi-section bioco-divi-timeline bioco-divi-width-{$container} bioco-divi-align-{$align}")),
            $rows
        );
    }

    private static function galleryStripSection(array $values): array {
        $columnsDesktop = self::modifier($values['columns_desktop'] ?? '', ['2', '3', '4'], '3');
        $columnsMobile  = self::modifier($values['columns_mobile']  ?? '', ['1', '2'], '1');
        $mediaRatio     = self::modifier($values['media_ratio']    ?? '', ['1:1', '4:3', '16:9'], '4:3');
        $mediaFit       = self::modifier($values['media_fit']      ?? '', ['cover', 'contain'], 'cover');
        $gap            = self::modifier($values['gap']            ?? '', ['sm', 'md', 'lg', 'xl'], 'lg');
        $rounded        = self::modifier($values['rounded']        ?? '', ['none', 'sm', 'md', 'lg', 'xl'], 'lg');

        $ratioClass   = 'bioco-divi-gallery-strip-row--ratio-' . str_replace(':', '_', $mediaRatio);
        $fitClass     = 'bioco-divi-gallery-strip-row--fit-' . $mediaFit;
        $roundedClass = 'bioco-divi-gallery-strip-row--rounded-' . $rounded;
        $rowClass     = "bioco-divi-row bioco-divi-gallery-strip-row bioco-divi-gallery-strip-row--desktop-{$columnsDesktop}--mobile-{$columnsMobile} {$ratioClass} {$fitClass} {$roundedClass} bioco-divi-gap-{$gap}";

        $rows = [];
        $rows[] = self::withChildren(
            bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-divi-row bioco-divi-gallery-strip-header')),
            [self::withChildren(
                bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-divi-content')),
                self::headerBlocks($values)
            )]
        );

        $frames = [];
        foreach ($values['gallery'] ?? [] as $id) {
            if (!is_int($id) && !is_string($id)) continue;
            $attachmentId = (int) $id;
            if ($attachmentId <= 0) continue;

            $alt = '';
            if (function_exists('get_post_meta')) {
                $alt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            }
            if ($alt === '') {
                $alt = (string) ($values['title'] ?? '');
            }

            $img = self::imageBlock($attachmentId, $alt, 'bioco-divi-gallery-strip-image');
            if (!$img) continue;
            $frames[] = self::withChildren(
                bioco_import_divi_block('divi/column', self::columnAttr('1_' . $columnsDesktop, 'bioco-divi-gallery-strip-frame')),
                [$img]
            );
        }

        if ($frames) {
            $structure = implode(',', array_fill(0, count($frames), '1_' . $columnsDesktop));
            $rows[] = self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr($structure, $rowClass)),
                $frames
            );
        }

        $buttons = [];
        foreach ($values['buttons'] ?? [] as $button) {
            if (!is_array($button)) continue;
            $block = self::buttonBlock($button);
            if ($block) $buttons[] = $block;
        }
        if ($buttons) {
            $rows[] = self::withChildren(
                bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-divi-row bioco-divi-gallery-strip-actions')),
                [self::withChildren(
                    bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-divi-content')),
                    $buttons
                )]
            );
        }

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-divi-section bioco-divi-gallery-strip')),
            $rows
        );
    }

    private static function cardsGridSection(array $values): array {
        $columnsDesktop = self::modifier($values['columns_desktop'] ?? '', ['2', '3', '4'], '3');
        $columnsMobile  = self::modifier($values['columns_mobile']  ?? '', ['1', '2'], '1');
        $cardStyle      = self::modifier($values['card_style']     ?? '', ['plain', 'soft', 'outlined'], 'soft');
        $mediaRatio     = self::modifier($values['media_ratio']    ?? '', ['1:1', '4:3', '3:4'], '3:4');
        $mediaFit       = self::modifier($values['media_fit']      ?? '', ['cover', 'contain'], 'cover');
        $gap            = self::modifier($values['gap']            ?? '', ['sm', 'md', 'lg', 'xl'], 'lg');
        $rounded        = self::modifier($values['rounded']        ?? '', ['none', 'sm', 'md', 'lg', 'xl'], 'md');

        $ratioClass   = 'bioco-divi-cards-grid-row--ratio-' . str_replace(':', '_', $mediaRatio);
        $fitClass     = 'bioco-divi-cards-grid-row--fit-' . $mediaFit;
        $roundedClass = 'bioco-divi-cards-grid-row--rounded-' . $rounded;
        $rowClass     = "bioco-divi-row bioco-divi-cards-grid-row bioco-divi-cards-grid-row--desktop-{$columnsDesktop}--mobile-{$columnsMobile} {$ratioClass} {$fitClass} {$roundedClass} bioco-divi-gap-{$gap}";

        $headerRow = self::withChildren(
            bioco_import_divi_block('divi/row', self::rowAttr('4_4', 'bioco-divi-row bioco-divi-cards-grid-header')),
            [self::withChildren(
                bioco_import_divi_block('divi/column', self::columnAttr('4_4', 'bioco-divi-content')),
                self::headerBlocks($values)
            )]
        );

        $cardColumns = [];
        foreach ($values['cards'] ?? [] as $card) {
            if (!is_array($card)) continue;
            $cardTitle = trim((string) ($card['title'] ?? ''));
            if ($cardTitle === '') continue;

            $children = [];
            $img = self::imageBlock(
                isset($card['image']) ? (int) $card['image'] : null,
                (string) ($card['image_alt'] ?? ''),
                'bioco-divi-card-image'
            );
            if ($img) $children[] = $img;

            $children[] = self::headingBlock($cardTitle, 'h3', 'bioco-divi-card-title');

            $cardText = trim((string) ($card['text'] ?? ''));
            if ($cardText !== '') {
                $children[] = self::textBlock($cardText, 'bioco-divi-card-text');
            }

            $href = trim((string) ($card['href'] ?? ''));
            if (self::hrefKind($href) !== 'unsafe') {
                $btn = self::buttonBlock(['text' => $cardTitle, 'href' => $href, 'variant' => 'primary']);
                if ($btn) $children[] = $btn;
            }

            $cardColumns[] = self::withChildren(
                bioco_import_divi_block('divi/column', self::columnAttr('1_' . $columnsDesktop, "bioco-divi-cards-grid-card bioco-divi-cards-grid-card--{$cardStyle}")),
                $children
            );
        }

        $structure = $cardColumns ? implode(',', array_fill(0, count($cardColumns), '1_' . $columnsDesktop)) : '4_4';
        $gridRow = self::withChildren(
            bioco_import_divi_block('divi/row', self::rowAttr($structure, $rowClass)),
            $cardColumns
        );

        return self::withChildren(
            bioco_import_divi_block('divi/section', self::classAttr('bioco-divi-section bioco-divi-cards-grid')),
            [$headerRow, $gridRow]
        );
    }

    private static function pricingTableSection(array $values): array {
        $container = self::modifier($values['container_width'] ?? '', ['md', 'lg', 'xl'], 'xl');
        $workSuffix = (string) ($values['work_suffix'] ?? '');

        $defaultLabels = [
            'basket_column_label' => 'Gemüsekorb',
            'persons_column_label' => 'Personen',
            'annual_price_column_label' => 'Jahrespreis',
            'share_cost_column_label' => 'Anteilsscheine Kosten',
            'work_column_label' => 'Mitarbeit pro Jahr',
        ];
        $labels = [];
        foreach ($defaultLabels as $key => $default) {
            $labels[$key] = (string) ($values[$key] ?? '') !== '' ? (string) $values[$key] : $default;
        }

        $children = self::headerBlocks($values);

        $tiersHtml = '';
        foreach ($values['tiers'] ?? [] as $tier) {
            if (!is_array($tier)) continue;
            $name = trim((string) ($tier['name'] ?? ''));
            if ($name === '') continue;
            $shares = (string) ($tier['shares'] ?? '');
            $persons = (int) ($tier['persons'] ?? 0);
            $price = (string) ($tier['price'] ?? '');
            $sharecost = (string) ($tier['sharecost'] ?? '');
            $work = (string) ($tier['work'] ?? '');

            $tiersHtml .= '<tr>';
            $tiersHtml .= '<td><strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>';
            if ($shares !== '') {
                $tiersHtml .= '<div class="cms-pricing-table-shares">' . htmlspecialchars($shares, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            $tiersHtml .= '</td>';
            $tiersHtml .= '<td>' . self::personIconsHtml($persons) . '</td>';
            $tiersHtml .= '<td>' . htmlspecialchars($price, ENT_QUOTES, 'UTF-8') . '</td>';
            $tiersHtml .= '<td>' . htmlspecialchars($sharecost, ENT_QUOTES, 'UTF-8') . '</td>';
            $tiersHtml .= '<td>' . htmlspecialchars($work, ENT_QUOTES, 'UTF-8');
            if ($workSuffix !== '') {
                $tiersHtml .= '<br /><span class="cms-pricing-table-work-suffix">' . htmlspecialchars($workSuffix, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $tiersHtml .= '</td>';
            $tiersHtml .= '</tr>';
        }

        if ($tiersHtml !== '') {
            $html = '<div class="pricing-table"><table><thead><tr>';
            $html .= '<th scope="col">' . htmlspecialchars($labels['basket_column_label'], ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '<th scope="col">' . htmlspecialchars($labels['persons_column_label'], ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '<th scope="col">' . htmlspecialchars($labels['annual_price_column_label'], ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '<th scope="col">' . htmlspecialchars($labels['share_cost_column_label'], ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '<th scope="col">' . htmlspecialchars($labels['work_column_label'], ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '</tr></thead><tbody>' . $tiersHtml . '</tbody></table></div>';
            $children[] = self::textBlock($html, 'bioco-divi-pricing-table-body');
        }

        foreach ($values['buttons'] ?? [] as $button) {
            if (!is_array($button)) continue;
            $block = self::buttonBlock($button);
            if ($block) $children[] = $block;
        }

        return self::singleColumnSection(
            "bioco-divi-section bioco-divi-pricing-table bioco-divi-width-{$container}",
            'bioco-divi-content',
            $children
        );
    }

    private static function personIconsHtml(int $count): string {
        $count = max(0, $count);
        if ($count === 0) return '';
        $iconsPerRow = $count === 4 ? 2 : $count;
        $rows = $count === 4 ? 2 : 1;
        $svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="person-icon" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4" fill="var(--wp--preset--color--bioco-green)" stroke="var(--wp--preset--color--bioco-green-dark)" stroke-width="1"/><path d="M6 20C6 16 8 14 12 14C16 14 18 16 18 20" stroke="var(--wp--preset--color--bioco-green)" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
        $label = $count === 1 ? '1 Person' : $count . ' Personen';
        $html = '<div class="person-icons" role="img" aria-label="' . $label . '">';
        for ($row = 0; $row < $rows; $row++) {
            $html .= '<div class="person-icons-row">';
            for ($col = 0; $col < $iconsPerRow; $col++) {
                $iconNumber = $row * $iconsPerRow + $col;
                if ($iconNumber >= $count) continue;
                $html .= $svg;
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function accordionSection(array $values): array {
        $children = [];
        foreach ($values['items'] ?? [] as $item) {
            if (!is_array($item)) continue;
            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));
            if ($title === '' && $body === '') continue;
            $html = '<details>';
            $html .= '<summary>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</summary>';
            if ($body !== '') {
                $html .= '<div>' . $body . '</div>';
            }
            $html .= '</details>';
            $children[] = self::textBlock($html, 'bioco-divi-accordion-item');
        }

        return self::singleColumnSection(
            'bioco-divi-section bioco-divi-accordion demeter-accordion',
            'bioco-divi-content',
            $children
        );
    }

    private static function withChildren(array $parent, array $children): array {
        $parent['innerBlocks'] = $children;
        $parent['innerContent'] = array_fill(0, count($children), null);
        return $parent;
    }
}
