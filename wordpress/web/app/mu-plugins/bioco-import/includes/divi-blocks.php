<?php
/**
 * Divi block serialization helpers.
 *
 * Converts lightweight Divi block descriptors into standard WordPress block
 * arrays (blockName, attrs, innerBlocks, innerHTML, innerContent) so that
 * WordPress serialize_blocks() emits the native Divi 5 comment markup:
 *   <!-- wp:divi/section -->...<!-- /wp:divi/section -->
 */

/**
 * Build a single WordPress block array from a Divi block descriptor.
 *
 * @param string $name    One of divi/section, divi/row, divi/column, divi/text,
 *                         divi/heading, divi/image, divi/button.
 * @param array  $attrs   Block attributes (rich HTML lives here only).
 * @param array  $children Child blocks for container types.
 * @return array Standard WordPress block array.
 * @throws InvalidArgumentException
 */
function bioco_import_divi_block(string $name, array $attrs = [], array $children = []): array
{
    if (!in_array($name, ['divi/section', 'divi/row', 'divi/column', 'divi/text', 'divi/heading', 'divi/image', 'divi/button'], true)) {
        throw new InvalidArgumentException("Unsupported Divi block: {$name}");
    }

    $innerBlocks = $children;

    if ($innerBlocks) {
        // Container: one null marker per child so serialize_blocks preserves order.
        $innerContent = array_fill(0, count($innerBlocks), null);
        $innerHTML = '';
    } else {
        // Leaf: force paired comments via non-empty string innerContent.
        $innerContent = ["\n"];
        $innerHTML = '';
    }

    return [
        'blockName'    => $name,
        'attrs'        => $attrs,
        'innerBlocks'  => $innerBlocks,
        'innerHTML'    => $innerHTML,
        'innerContent' => $innerContent,
    ];
}

/**
 * Serialize a list of Divi blocks into WordPress block markup.
 *
 * Delegates to WordPress serialize_blocks(). Tests must define a stub.
 *
 * @param array $blocks List of block arrays from bioco_import_divi_block().
 * @return string Block markup, empty string for empty list.
 */
function bioco_import_serialize_divi_blocks(array $blocks): string
{
    if (!$blocks) {
        return '';
    }
    return serialize_blocks($blocks);
}
