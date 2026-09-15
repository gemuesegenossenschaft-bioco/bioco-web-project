<?php
/** Replace legacy marker leaves only; preserve surrounding editorial blocks. */
if (!defined('ABSPATH')) exit;

function bioco_import_native_tree(array $blocks, int &$count): array {
    foreach ($blocks as &$block) {
        if (($block['blockName'] ?? '') === 'divi/text') {
            $html = $block['attrs']['content']['innerContent']['desktop']['value'] ?? '';
            if (is_string($html) && str_contains($html, 'bioco-dynamic')) {
                $tag = new WP_HTML_Tag_Processor($html);
                if (!$tag->next_tag(['tag_name' => 'DIV', 'class_name' => 'bioco-dynamic'])) {
                    throw new RuntimeException('Invalid legacy module marker.');
                }
                // A mixed editorial text module cannot safely become one native module.
                if (!preg_match('~^\s*<div\b[^>]*>\s*</div>\s*$~s', $html)) {
                    throw new RuntimeException('Legacy marker shares a text module with editorial content.');
                }
                $component = $tag->get_attribute('data-bioco-component');
                if (!is_string($component) || !isset(bioco_dynamic_components()[$component])) {
                    throw new RuntimeException('Unknown legacy component.');
                }
                $encoded = $tag->get_attribute('data-bioco-props');
                $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
                $values = $decoded !== false ? json_decode($decoded, true) : null;
                if (!is_array($values)) throw new RuntimeException('Invalid legacy component values.');
                $fields = array_diff_key($values, ['anchor' => true, 'className' => true]);
                $validated = bioco_native_validate_values($component, $fields);
                if (is_wp_error($validated)) throw new RuntimeException($validated->get_error_message());
                $attrs = $block['attrs'];
                unset($attrs['content']);
                foreach ($fields as $field => $value) {
                    $attrs[$field] = ['innerContent' => ['desktop' => ['value' => $value]]];
                }
                if (!empty($values['anchor'])) $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $values['anchor'];
                if (!empty($values['className'])) {
                    $existing = $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '';
                    $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'] = trim($existing . ' ' . $values['className']);
                }
                $block = bioco_import_divi_block('bioco-divi/' . str_replace('_', '-', $component), $attrs);
                $count++;
            }
        }
        if (!empty($block['innerBlocks'])) $block['innerBlocks'] = bioco_import_native_tree($block['innerBlocks'], $count);
    }
    return $blocks;
}

function bioco_import_native_content(string $content): array {
    $count = 0;
    $blocks = bioco_import_native_tree(parse_blocks($content), $count);
    return [$count ? serialize_blocks($blocks) : $content, $count];
}

/** Compare-and-swap works on staging's MyISAM tables as well as InnoDB. */
function bioco_import_native_save($page, string $content): void {
    global $wpdb;
    // BINARY prevents a case/accent-insensitive database collation accepting an edit.
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->posts} SET post_content = %s, post_modified = %s, post_modified_gmt = %s WHERE ID = %d AND BINARY post_content = BINARY %s",
        $content, current_time('mysql'), current_time('mysql', true), $page->ID, $page->post_content
    ));
    if ($updated === false) throw new RuntimeException('Cannot save migrated page: ' . $page->post_name);
    if ($updated !== 1) throw new RuntimeException('Concurrent edit: ' . $page->post_name);
    clean_post_cache($page->ID);
    // Retain the pre-migration content as a normal WordPress revision. This uses
    // the captured post rather than re-reading a row another editor may now save.
    if (wp_revisions_enabled($page)) {
        $revision = _wp_put_post_revision($page);
        if (is_wp_error($revision)) throw new RuntimeException('Page migrated, but revision failed: ' . $page->post_name);
    }
}
