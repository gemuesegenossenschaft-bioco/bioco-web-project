<?php
/**
 * ACF field resolution + ACF-block Gutenberg serialization.
 * ============================================================================
 * This is the "hard case" the import spec calls out explicitly: ACF blocks
 * store their values as a single block comment
 *
 *   <!-- wp:bioco/hero {"name":"bioco/hero","data":{"headline":"...",
 *        "_headline":"field_bioco_hero_headline"},"mode":"preview"} /-->
 *
 * with each value paired to a "_name" => field_key companion entry (ACF's
 * documented programmatic format), and repeaters use a row-index format
 * (name_0_subname + companion "_name_0_subname" keys, plus a numeric row
 * count under the bare name).
 *
 * WHERE THE KEYS COME FROM (never hardcoded)
 * -------------------------------------------------------------------------
 * Every bioco-core block field group clones from group_bioco_section_common
 * via ACF "clone" fields (e.g. field_bioco_hero_media_clone clones
 * field_bioco_section_image + field_bioco_section_image_alt). ACF renames
 * each cloned field's *key* by combining the clone field's own key with the
 * source field's key — an internal algorithm implemented in ACF's own
 * class-acf-field-clone.php that is not part of the public/documented API
 * and has changed across ACF versions. Hand-reimplementing that renaming
 * here would be exactly the kind of fragile guesswork this importer must
 * avoid: it would silently emit wrong "_name" keys whenever ACF's internal
 * behaviour differs from our guess, and WordPress would then not recognise
 * the value as belonging to any field.
 *
 * Instead, this file asks ACF itself to resolve the field group at runtime
 * via acf_get_field_group() + acf_get_fields() — the same calls ACF's own
 * admin screens and block renderer use — which already expands clone fields
 * (and nested repeater sub_fields) to their real, final name/key. ACF loads
 * the acf-json/*.json files via bioco-core.php's acf/settings/load_json
 * filter, so "read the acf-json files to build the map" still happens; it
 * just happens through ACF's own loader instead of a hand-rolled JSON parser
 * that would have to duplicate ACF's clone-key algorithm to be correct.
 */

if (!defined('ABSPATH')) exit;

// Fully-resolved field list for an ACF field group (clone fields already
// expanded to their final name/key), or a WP_Error explaining why not.
function bioco_import_acf_group_fields($group_key) {
    static $cache = [];
    if (array_key_exists($group_key, $cache)) {
        return $cache[$group_key];
    }

    if (!function_exists('acf_get_field_group') || !function_exists('acf_get_fields')) {
        return $cache[$group_key] = new WP_Error(
            'bioco_import_acf_missing',
            'ACF ist nicht aktiv — Feldgruppen können nicht aufgelöst werden. Läuft `wp bioco` auf einer Site ohne aktives ACF-Plugin?'
        );
    }

    $group = acf_get_field_group($group_key);
    if (!$group) {
        return $cache[$group_key] = new WP_Error(
            'bioco_import_acf_group_missing',
            "ACF-Feldgruppe '{$group_key}' nicht gefunden (acf-json fehlt, Key falsch, oder Feldgruppe deaktiviert)."
        );
    }

    $fields = acf_get_fields($group);
    if (!is_array($fields)) {
        return $cache[$group_key] = new WP_Error(
            'bioco_import_acf_fields_missing',
            "ACF-Feldgruppe '{$group_key}' hat keine auflösbaren Felder."
        );
    }

    return $cache[$group_key] = $fields;
}

// Flat "field name" => "field key" map, top level only. Used for the
// self-verify existence checks (does a field with this NAME really exist on
// this block's field group?) and for simple reporting/diagnostics. The
// serializer below does NOT use this flattened form for repeaters — it walks
// the resolved $fields directly so sub_fields keep their own key/name pairs.
function bioco_import_acf_flat_map(array $fields) {
    $map = [];
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') continue;
        $map[$name] = $field['key'];
    }
    return $map;
}

// Field NAMES present in $values that have no matching field on $fields —
// callers report these as warnings rather than silently dropping content.
function bioco_import_acf_unmatched_fields(array $values, array $fields) {
    $known = [];
    foreach ($fields as $field) {
        if (($field['name'] ?? '') !== '') $known[] = $field['name'];
    }
    return array_values(array_diff(array_keys($values), $known));
}

/**
 * Builds the ACF block "data" array (the part inside {"name":...,"data":{...
 * }}) for $values (keyed by ACF field NAME) against the resolved $fields.
 * Values without a matching field are skipped here (the caller is expected
 * to have already reported them via bioco_import_acf_unmatched_fields()).
 *
 * Fields the plan does NOT supply are still written out with their own
 * default_value — including repeater rows — see the comment in the loop for
 * why omitting them would render as missing content rather than as a default.
 *
 * Repeaters use ACF's documented row-index programmatic format:
 *   data.{name}            = row count
 *   data._{name}           = repeater field key
 *   data.{name}_{i}_{sub}  = sub-field value for row i
 *   data._{name}_{i}_{sub} = sub-field key for row i
 */
function bioco_import_acf_block_data(array $values, array $fields) {
    $data = [];
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') continue;

        if (!array_key_exists($name, $values)) {
            // The plan does not supply this field. Write the field's own
            // default_value into the block data anyway, rather than omitting the
            // key and trusting ACF to substitute the default at render time.
            //
            // Why: an ACF block keeps its values in the serialized block
            // attributes. A key absent there has no value on THIS block
            // instance, and whether get_field() then falls back to
            // default_value is ACF-internal behaviour we would be relying on
            // invisibly. Because the render templates deliberately carry no
            // content fallbacks (CLAUDE.md: no fallback content), an unresolved
            // default does not degrade to placeholder text — it renders as a
            // MISSING heading or link, silently, on a live page.
            //
            $default = $field['default_value'] ?? null;
            if (($field['type'] ?? '') === 'repeater') {
                if (!is_array($default) || empty($default)) continue;
                $value = $default;
            } else {
                if ($default === null || $default === '' || is_array($default)) continue;
                $data[$name] = $default;
                $data['_' . $name] = $field['key'];
                continue;
            }
        } else {
            $value = $values[$name];
        }

        if (($field['type'] ?? '') === 'repeater') {
            $rows = is_array($value) ? array_values($value) : [];
            $data[$name] = count($rows);
            $data['_' . $name] = $field['key'];
            $subFields = is_array($field['sub_fields'] ?? null) ? $field['sub_fields'] : [];
            foreach ($rows as $i => $row) {
                if (!is_array($row)) continue;
                foreach ($subFields as $sub) {
                    $subName = $sub['name'] ?? '';
                    if ($subName === '' || !array_key_exists($subName, $row)) continue;
                    $data["{$name}_{$i}_{$subName}"] = $row[$subName];
                    $data["_{$name}_{$i}_{$subName}"] = $sub['key'];
                }
            }
            continue;
        }

        $data[$name] = $value;
        $data['_' . $name] = $field['key'];
    }
    return $data;
}

/**
 * Return the block name declared in block.json. Secure Custom Fields 6.9.5
 * keeps that name when register_block_type() loads the metadata, so serialized
 * comments must use the same bioco/* name as the runtime registry.
 */
function bioco_import_block_comment_name(array $blockJson) {
    return (string) ($blockJson['name'] ?? '');
}

// Reads + memoizes a bioco-core block.json by its directory name (e.g.
// 'hero' for blocks/hero/block.json).
function bioco_import_block_json($blockDirName) {
    static $cache = [];
    if (array_key_exists($blockDirName, $cache)) {
        return $cache[$blockDirName];
    }
    $path = BIOCO_IMPORT_BLOCKS_DIR . '/' . $blockDirName . '/block.json';
    if (!file_exists($path)) {
        return $cache[$blockDirName] = null;
    }
    $json = json_decode((string) file_get_contents($path), true);
    return $cache[$blockDirName] = is_array($json) ? $json : null;
}

/**
 * Full pipeline: bioco-core block directory name + ACF field-group key +
 * values (by ACF field name) -> one serialized "<!-- wp:bioco/... {...} /-->"
 * block comment, or null on failure (with $warnings/$errors explaining why).
 */
function bioco_import_serialize_acf_block($blockDirName, $acfGroupKey, array $values, array &$warnings, array &$errors) {
    $blockJson = bioco_import_block_json($blockDirName);
    if (!$blockJson) {
        $errors[] = "block.json fehlt für Block '{$blockDirName}' (erwartet unter blocks/{$blockDirName}/block.json).";
        return null;
    }

    $fields = bioco_import_acf_group_fields($acfGroupKey);
    if (is_wp_error($fields)) {
        $errors[] = $fields->get_error_message();
        return null;
    }

    foreach (bioco_import_acf_unmatched_fields($values, $fields) as $unmatched) {
        $warnings[] = "Feld '{$unmatched}' existiert nicht in ACF-Feldgruppe '{$acfGroupKey}' — Inhalt wurde NICHT geschrieben.";
    }

    $data = bioco_import_acf_block_data($values, $fields);
    $blockName = bioco_import_block_comment_name($blockJson);
    $attrs = ['name' => $blockName, 'data' => $data, 'mode' => 'preview'];

    // serialize_block() (WP core, wp-includes/blocks.php) applies the exact
    // block-comment JSON-escaping rules (--, <, >, &, \" -> \u escapes) and
    // the void/self-closing "/-->" form when innerContent is empty — reusing
    // it means this importer never has to hand-roll that escaping.
    return serialize_block([
        'blockName' => $blockName,
        'attrs' => $attrs,
        'innerBlocks' => [],
        'innerHTML' => '',
        'innerContent' => [],
    ]);
}

/**
 * Only the block-comment-name resolution step, exposed standalone so the
 * self-verify script (and `wp bioco verify`, which needs to recognise blocks
 * in already-saved post_content) can check block names without needing ACF
 * field values on hand.
 */
function bioco_import_registered_block_name($blockDirName) {
    $blockJson = bioco_import_block_json($blockDirName);
    if (!$blockJson) return null;
    return bioco_import_block_comment_name($blockJson);
}
