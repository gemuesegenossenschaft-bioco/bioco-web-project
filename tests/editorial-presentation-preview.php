<?php
/** Standalone parser and pure-converter checks. */
define('ABSPATH', __DIR__ . '/');
foreach (['class-wp-block-parser-block.php', 'class-wp-block-parser-frame.php', 'class-wp-block-parser.php'] as $file) {
    require __DIR__ . '/fixtures/wp-block-parser/' . $file;
}
require __DIR__ . '/../wordpress/web/app/mu-plugins/bioco-import/includes/editorial-presentation-preview.php';

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function block(string $name, array $attrs = [], string $inside = ''): string {
    return '<!-- wp:' . $name . ' ' . json_encode($attrs, JSON_UNESCAPED_SLASHES) . ' -->' . $inside . '<!-- /wp:' . $name . ' -->';
}
function classes(string $value): array {
    return ['module' => ['decoration' => ['attributes' => ['desktop' => ['value' => ['attributes' => [
        ['id' => 'saved-class-id', 'name' => 'class', 'value' => $value, 'adminLabel' => 'CSS Class'],
        ['id' => 'saved-anchor-id', 'name' => 'id', 'value' => 'source-anchor', 'adminLabel' => 'CSS ID'],
    ]]]]]]];
}

$heading = classes('bioco-divi-title authored-class');
$heading['title']['innerContent']['desktop']['value'] = 'Authored heading';
$heading['title']['decoration']['font']['font'] = [
    'desktop' => ['value' => ['color' => '#123456']], 'phone' => ['value' => ['size' => '19px']],
];
$heading['module']['decoration']['spacing']['desktop']['value']['margin']['top'] = '3px';
$text = classes('bioco-divi-text authored-text');
$text['content']['innerContent']['desktop']['value'] = '<p>Authored <a href="/kept">link</a></p>';
$text['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = '#654321';
$button = classes('bioco-divi-button');
$button['button']['innerContent']['desktop']['value']['text'] = 'Kept CTA';
$content = block('divi/heading', $heading) . block('divi/text', $text) . block('divi/button', $button);
$rich = block('divi/section', classes('bioco-divi-section bioco-divi-rich-text'),
    block('divi/row', classes('bioco-divi-row'), block('divi/column', classes('bioco-divi-content'), $content)));
$image = classes('bioco-divi-media-image');
$image['image']['innerContent']['desktop']['value'] = ['src' => 'https://example.invalid/source.jpg', 'alt' => 'Source alt', 'linkUrl' => '/kept-image-link'];
$image['module']['advanced']['sizing']['phone']['value']['width'] = '82%';
$mediaRowAttrs = classes('bioco-divi-row');
$mediaRowAttrs['module']['decoration']['layout']['desktop']['value']['display'] = 'block';
$media = block('divi/section', classes('bioco-divi-section bioco-divi-media-text bioco-divi-media-right'),
    block('divi/row', $mediaRowAttrs,
        block('divi/column', classes('bioco-divi-media'), block('divi/image', $image))
        . block('divi/column', classes('bioco-divi-content'), block('divi/text', classes('bioco-divi-text')))));
$untouched = block('divi/section', classes('bioco-home-hero'), block('divi/row', [], block('divi/column', [], $content)));
$parser = new WP_Block_Parser();
$before = $parser->parse($rich . $media . $untouched);
[$after, $report] = bioco_editorial_media_rich_preview($before);
check($report === ['sections' => 2, 'headings' => 1, 'texts' => 2, 'skipped' => []], 'family report');
check($after[2] === $before[2], 'unrelated home blocks untouched');
check($after[1]['innerBlocks'][0]['innerBlocks'][0] === $before[1]['innerBlocks'][0]['innerBlocks'][0], 'media image and source retained');
$newHeading = $after[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
$oldHeading = $before[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
check($newHeading['attrs']['title']['innerContent'] === $oldHeading['attrs']['title']['innerContent'], 'heading content retained');
check($newHeading['attrs']['title']['decoration']['font']['font']['desktop']['value']['color'] === '#123456', 'authored desktop color retained');
check($newHeading['attrs']['title']['decoration']['font']['font']['phone']['value']['size'] === '19px', 'authored phone size retained');
check($newHeading['attrs']['title']['decoration']['font']['font']['desktop']['value']['size'] === 'clamp(1.5rem, 3vw, 2rem)', 'native heading size filled');
check($newHeading['attrs']['title']['decoration']['font']['font']['desktop']['value']['lineHeight'] === '1.7em', 'native heading retains observed line height');
check($newHeading['attrs']['module']['decoration']['spacing']['desktop']['value']['margin'] === ['bottom' => 'clamp(12px, 1.5vw, 16px)', 'top' => '3px'], 'native margin merged at observed size');
check($newHeading['attrs']['css']['desktop']['value']['mainElement'] === 'padding-bottom:0px;', 'Heading child keeps observed zero padding through actual native Advanced CSS path');
check(bioco_editorial_preview_classes($newHeading) === ['authored-class'], 'only legacy title class removed');
check($newHeading['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'][1]['value'] === 'source-anchor', 'anchor retained');
$newText = $after[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1];
check($newText['attrs']['content']['innerContent'] === $text['content']['innerContent'], 'HTML and link retained');
check($newText['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] === '#654321', 'authored text color retained');
check(bioco_editorial_preview_classes($newText) === ['authored-text'], 'only legacy text class removed');
check($after[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][2] === $before[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][2], 'button untouched');
[$full, $fullReport] = bioco_editorial_media_rich_full_preview($before);
check($fullReport['geometry'] === ['sections' => 2, 'images' => 1], 'full geometry report');
check($full[2] === $before[2], 'full preview leaves home untouched');
$richSection = $full[0];
check(bioco_editorial_preview_classes($richSection) === [], 'legacy rich section classes removed');
check($richSection['attrs']['module']['decoration']['background']['desktop']['value']['color'] === '#F5F1E8', 'native section background');
check($richSection['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['desktop']['value'] === ['width' => '80%', 'maxWidth' => '1080px', 'alignment' => 'center'], 'rich row keeps observed Divi width');
check($richSection['attrs']['module']['decoration']['spacing']['desktop']['value']['padding'] === ['top' => '54px', 'bottom' => '54px', 'left' => '0px', 'right' => '0px'], 'desktop section keeps observed padding');
check($richSection['attrs']['module']['decoration']['spacing']['phone']['value']['padding'] === ['top' => '32px', 'bottom' => '32px', 'left' => '6vw', 'right' => '6vw'], 'phone section keeps observed padding');
$richLeaf = $richSection['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];
check($richLeaf['attrs']['title']['decoration']['font']['font']['desktop']['value']['color'] === '#123456', 'full preview preserves authored heading color');
$defaultHeading = $before;
unset($defaultHeading[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']['desktop']['value']['color']);
[$defaultHeadingAfter] = bioco_editorial_media_rich_full_preview($defaultHeading);
check($defaultHeadingAfter[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']['desktop']['value']['color'] === '#1A1A1A', 'native heading retains observed color when unauthored');
check($richSection['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][2] === $before[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][2], 'full preview preserves CTA');
check($richSection['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['lineHeight'] === '1.7em', 'native Text line-height reaches actual block tree');
$mediaSection = $full[1];
$mediaRow = $mediaSection['innerBlocks'][0];
check($mediaRow['attrs']['module']['decoration']['layout']['desktop']['value']['gridTemplateColumns'] === 'repeat(2, minmax(0, 1fr))', 'native desktop grid');
check($mediaRow['attrs']['module']['decoration']['layout']['desktop']['value']['display'] === 'grid', 'known legacy block display converted');
check($mediaRow['attrs']['module']['decoration']['layout']['phone']['value']['gridTemplateColumns'] === 'minmax(0, 1fr)', 'native phone stack');
check($mediaRow['attrs']['module']['decoration']['sizing']['desktop']['value']['maxWidth'] === '1080px', 'image row keeps observed max width');
$textOnly = [$before[1]];
$textOnly[0]['innerBlocks'][0]['innerBlocks'] = [$textOnly[0]['innerBlocks'][0]['innerBlocks'][1]];
[$textOnlyAfter] = bioco_editorial_media_rich_full_preview($textOnly);
check($textOnlyAfter[0]['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['desktop']['value']['maxWidth'] === '1160px', 'text-only media row retains wider observed max width');
check($mediaRow['innerBlocks'][0]['attrs']['module']['decoration']['order']['desktop']['value']['order'] === '2', 'media right desktop order');
check($mediaRow['innerBlocks'][0]['attrs']['module']['decoration']['order']['phone']['value']['order'] === '0', 'source phone order');
check($mediaRow['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['desktop']['value']['width'] === '94.5%', 'media column retains Divi float width inside desktop grid');
check($mediaRow['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['phone']['value']['width'] === '100%', 'media column fills phone row');
check($mediaRow['innerBlocks'][0]['attrs']['module']['decoration']['spacing']['phone']['value']['margin']['bottom'] === '30px', 'media column retains Divi phone bottom margin');
$newImage = $mediaRow['innerBlocks'][0]['innerBlocks'][0];
check($newImage['attrs']['image']['innerContent'] === $image['image']['innerContent'], 'image src/alt/link retained');
check($newImage['attrs']['module']['advanced']['sizing']['phone']['value']['width'] === '82%', 'authored phone image width retained');
check($newImage['attrs']['module']['advanced']['sizing']['desktop']['value']['aspectRatio'] === ['width' => '4', 'height' => '3'], 'native image aspect ratio');
check($newImage['attrs']['image']['decoration']['fit']['desktop']['value']['objectFit'] === 'cover', 'native image fit');
check($newImage['attrs']['image']['decoration']['border']['desktop']['value']['radius']['topLeft'] === '24px', 'native image radius');
check(!in_array('bioco-divi-media-image', bioco_editorial_preview_classes($newImage), true), 'legacy image CSS class removed');
$authoredGrid = $before;
$authoredGrid[1]['innerBlocks'][0]['attrs']['module']['decoration']['layout']['phone']['value']['gridTemplateColumns'] = 'minmax(0, 2fr)';
[$keptGrid] = bioco_editorial_media_rich_full_preview($authoredGrid);
check($keptGrid[1]['innerBlocks'][0]['attrs']['module']['decoration']['layout']['phone']['value']['gridTemplateColumns'] === 'minmax(0, 2fr)', 'authored phone grid retained');
$inherited = $before;
$inherited[0]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['top'] = '71px';
$inherited[0]['attrs']['module']['decoration']['spacing']['tablet']['value']['padding']['bottom'] = '39px';
$inherited[0]['attrs']['module']['decoration']['spacing']['phone']['value']['padding']['right'] = null;
$inherited[1]['innerBlocks'][0]['attrs']['module']['decoration']['layout']['tablet']['value']['columnGap'] = '9px';
$inherited[1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['tablet']['value']['width'] = '83%';
$inherited[1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['order']['tablet']['value']['order'] = '0';
[$inheritedAfter] = bioco_editorial_media_rich_full_preview($inherited);
$phonePadding = $inheritedAfter[0]['attrs']['module']['decoration']['spacing']['phone']['value']['padding'];
check(!array_key_exists('top', $phonePadding) && !array_key_exists('bottom', $phonePadding), 'phone defaults do not mask authored desktop or tablet padding');
check(array_key_exists('right', $phonePadding) && $phonePadding['right'] === null && $phonePadding['left'] === '6vw', 'explicit null and unrelated phone default preserved');
check(!isset($inheritedAfter[1]['innerBlocks'][0]['attrs']['module']['decoration']['layout']['phone']['value']['columnGap']), 'phone gap inherits authored tablet gap');
check(!isset($inheritedAfter[1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['phone']['value']['width']), 'phone width inherits authored tablet width');
check(!isset($inheritedAfter[1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['order']['phone']['value']['order']), 'phone order inherits authored tablet zero');
$blank = $before;
$blank[1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['phone']['value']['width'] = '';
[$blankAfter] = bioco_editorial_media_rich_full_preview($blank);
check($blankAfter[1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['decoration']['sizing']['phone']['value']['width'] === '', 'explicit blank phone control preserved');
$nullDesktop = $before;
$nullDesktop[0]['attrs']['module']['decoration']['background']['desktop']['value'] = null;
try {
    bioco_editorial_media_rich_full_preview($nullDesktop);
    throw new RuntimeException('explicit null desktop value was overwritten');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Unsupported desktop value.', 'explicit null desktop value fails closed');
}
$preset = $before;
$preset[0]['attrs']['modulePreset'] = ['editor-owned-preset'];
try {
    bioco_editorial_media_rich_full_preview($preset);
    throw new RuntimeException('module preset was accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Preset-backed media/rich section needs effective style capture.', 'module preset fails closed');
}
$groupPreset = $before;
$groupPreset[1]['innerBlocks'][0]['innerBlocks'][1]['attrs']['content']['decoration']['bodyFont']['body']['presetId'] = ['editor-group-preset'];
try {
    bioco_editorial_media_rich_full_preview($groupPreset);
    throw new RuntimeException('nested group preset was accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Preset-backed media/rich section needs effective style capture.', 'group preset fails closed');
}
$unsupportedGrid = $before;
$unsupportedGrid[1]['innerBlocks'][0]['attrs']['module']['decoration']['layout']['desktop']['value']['display'] = 'flex';
try {
    bioco_editorial_media_rich_full_preview($unsupportedGrid);
    throw new RuntimeException('unknown layout display was accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Unknown authored media row display.', 'unknown layout fails closed');
}
[$fullAgain, $fullSecond] = bioco_editorial_media_rich_full_preview($full);
check($fullAgain === $full && $fullSecond['geometry']['sections'] === 0, 'full preview idempotent');
[$again, $secondReport] = bioco_editorial_media_rich_preview($after);
check($again === $after && $secondReport['headings'] === 0 && $secondReport['texts'] === 0, 'idempotent leaf conversion');
$wrapped = $parser->parse(block('divi/placeholder', [], $rich));
[$wrappedAfter, $wrappedReport] = bioco_editorial_media_rich_preview($wrapped);
check($wrappedReport['sections'] === 1 && $wrappedReport['headings'] === 1, 'real Divi placeholder nesting traversed');
function first_heading(array $blocks): ?array {
    foreach ($blocks as $candidate) {
        if (($candidate['blockName'] ?? '') === 'divi/heading') return $candidate;
        $nested = first_heading($candidate['innerBlocks'] ?? []);
        if ($nested !== null) return $nested;
    }
    return null;
}
check((first_heading($wrappedAfter)['attrs']['title']['decoration']['font']['font']['desktop']['value']['weight'] ?? null) === '700', 'nested heading changed');
$authoredHeadingCss = $before;
$authoredHeadingCss[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['css']['desktop']['value']['mainElement'] = 'border-color:red;';
try {
    bioco_editorial_media_rich_preview($authoredHeadingCss);
    throw new RuntimeException('authored heading CSS was accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Authored heading CSS needs review.', 'authored Heading CSS fails closed');
}
$unsupported = $before;
$unsupported[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']['desktop']['value'] = 'opaque-editor-value';
try {
    bioco_editorial_media_rich_preview($unsupported);
    throw new RuntimeException('unsupported native attribute was accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Unsupported desktop value.', 'unknown native controls fail closed');
}
$responsive = $before;
$responsive[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['module']['advanced']['htmlAttributes']['phone']['value']['class'] = 'bioco-divi-title';
try {
    bioco_editorial_media_rich_preview($responsive);
    throw new RuntimeException('responsive legacy class was accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Responsive legacy class requires separate review.', 'responsive class fails closed');
}
echo "editorial-presentation-preview: passed\n";
