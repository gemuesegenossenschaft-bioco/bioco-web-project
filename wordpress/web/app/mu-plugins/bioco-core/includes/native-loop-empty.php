<?php
/** Conditional empty branches; native Divi modules own their content and design. */
if (!defined('ABSPATH')) exit;

final class Bioco_Native_Loop_Empty {
    private static int $projecting = 0;

    public static function has_class(array $attrs, string $class): bool {
        $value = $attrs['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [];
        if (is_string($value)) $value = json_decode($value, true) ?: [];
        return is_array($value) && is_string($value['class'] ?? null)
            && in_array($class, preg_split('/\s+/', $value['class'], -1, PREG_SPLIT_NO_EMPTY), true);
    }

    /** Rendering must not alter the saved branch's children or runtime context. */
    private static function copy_branch(WP_Block $block): WP_Block {
        $copy = clone $block;
        $children = [];
        foreach ($block->inner_blocks as $child) $children[] = self::copy_branch($child);
        $copy->inner_blocks = new WP_Block_List($children, $copy->context);
        $copy->parsed_block['innerBlocks'] = array_map(static fn(WP_Block $child): array => $child->parsed_block, $children);
        return $copy;
    }

    /** Clone ancestor paths and complete saved empty branches, retaining IDs/context. */
    public static function select(WP_Block $block): ?WP_Block {
        if (self::has_class($block->parsed_block['attrs'] ?? [], 'bioco-loop-empty')) return self::copy_branch($block);
        $children = [];
        foreach ($block->inner_blocks as $child) {
            $selected = self::select($child);
            if ($selected !== null) $children[] = $selected;
        }
        if (!$children) return null;
        $copy = clone $block;
        $copy->inner_blocks = new WP_Block_List($children, $copy->context);
        $copy->inner_content = array_fill(0, count($children), null);
        $copy->inner_html = '';
        $copy->parsed_block['innerBlocks'] = array_map(static fn(WP_Block $child): array => $child->parsed_block, $children);
        $copy->parsed_block['innerContent'] = $copy->inner_content;
        $copy->parsed_block['innerHTML'] = '';
        return $copy;
    }

    private static function in_record_loop(WP_Block $block): bool {
        $store = '\\ET\\Builder\\FrontEnd\\BlockParser\\BlockParserStore';
        if (!class_exists($store)) return false;
        $id = $block->parsed_block['id'] ?? '';
        $instance = $block->parsed_block['storeInstance'] ?? null;
        $seen = [];
        while ($id !== '' && !isset($seen[$id])) {
            $seen[$id] = true;
            $parent = $store::get_parent($id, $instance);
            if (!$parent) return false;
            $attrs = (array) ($parent->attrs ?? []);
            if (self::has_class($attrs, 'bioco-record-loop') && !empty($attrs['__loop_source_id'])) return true;
            $id = (string) ($parent->id ?? '');
        }
        return false;
    }

    public static function filter_output(string $output, array $attrs, WP_Block $block, $elements, array $defaults): string {
        if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) return $output;
        if ($block->name === 'divi/row' && self::has_class($attrs, 'bioco-record-loop') && !empty($attrs['__loop_no_results'])) {
            $renderer = '\\ET\\Builder\\Packages\\ModuleLibrary\\Row\\RowModule';
            if (!class_exists($renderer)) return $output;
            $content = '';
            self::$projecting++;
            try {
                foreach ($block->inner_blocks as $column) {
                    $selected = self::select($column);
                    if ($selected !== null) $content .= $selected->render();
                }
            } finally { self::$projecting--; }
            // Module::render replaces no-result children a second time. Use the
            // same native row callback with that runtime flag removed; no fixed markup.
            unset($attrs['__loop_no_results']);
            return $renderer::render_callback($attrs, $content, $block, $elements, $defaults);
        }
        if (self::$projecting === 0 && self::has_class($attrs, 'bioco-loop-empty') && self::in_record_loop($block)) return '';
        return $output;
    }
}

add_filter('divi_loop_rendered_output', [Bioco_Native_Loop_Empty::class, 'filter_output'], 30, 5);
