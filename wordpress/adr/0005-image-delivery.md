# Image delivery with Divi and Modern Image Formats

## Design target

Editors upload normal photos. Originals, attachment IDs, authored metadata and saved Divi settings survive. WordPress owns format generation and responsive candidates; Divi owns appearance. Existing-image processing remains a separate coordinated operation.

## Alternatives before implementation

A. Configure Modern Image Formats only. No project API: upload through the Media Library. Smallest maintenance cost, but the local Divi 5.15 test served JPEGs with defaults. Enabling every fallback size produced stale WebP crop mappings after Divi generated its breakpoint sizes. Rejected.

B. Use the plugin and WordPress responsive candidates. `bioco_image_delivery_use_core_responsive_sizes(): void` removes Divi's two global candidate filters only while the plugin is active. Callers still use native Divi Image modules or `wp_get_attachment_image($id, 'large', false, $attributes)`. No custom converter, image storage or content migration. Sacrifice: Divi's fixed breakpoint candidate generation is replaced by WordPress srcset/sizes. Layout and module sizing remain Divi settings. Selected for runtime verification.

C. Build a separate converter and picture renderer. `generate($attachment_id)`, `render($attachment_id, $attributes)`, `regenerate($ids)`, `restore($manifest)`. Could precisely control budgets and background images, but creates a second image registry, more failure recovery and extra maintenance. Rejected unless B fails acceptance.

## Depth and rationale

B hides the known integration conflict behind one lifecycle callback. It reuses the maintained converter and core rendering APIs instead of adding another media pipeline. It changes candidate selection site-wide when the plugin is active, so test native Image, portrait/card, gallery, missing media, plugin deactivation and mobile/desktop selection. A is smaller; C permits tighter per-use compression. Neither advantage justifies incorrect crops or a new conversion subsystem.

## Implementation

1. Add the conditional responsive-filter compatibility callback.
2. Use the WordPress image renderer for gallery attachments; retain URL-only legacy entries.
3. Verify plugin configuration with originals plus fallback renditions, extra fallback sizes disabled.
4. Verify real local uploads, existing-image regeneration, actual browser sources and original hashes. Shared staging verification and approved promotion remain required.

Sources: [Modern Image Formats](https://wordpress.org/plugins/webp-uploads/), installed Divi 5.15.0 source, local WordPress 7.1.3 experiment. Plugin evaluated: 2.7.1. Licensed sources and private runtime evidence stay outside Git.

## Local verification

On WordPress 7.1.3 with Divi 5.15.0 and Modern Image Formats 2.7.1, four native Image modules produced 20 WebP candidates. Each decoded candidate matched its width descriptor and the JPEG fallback dimensions. Every WebP was smaller than its matching JPEG. Original file hashes, IDs, URLs, titles, captions and alt text remained unchanged. A new JPEG upload also generated a WebP source and retained its caption and alt text. Disabling the converter restored JPEG delivery for all four modules.

Browser checks at 1440px and 390px selected WebP for the four modules. The browser had a warm cache and device pixel ratio 2, so this does not establish a cold mobile transfer budget. Shared staging conversion, resumable existing-image batches, orientation/transparency checks and editor failure guidance remain outside this compatibility change. No converter was installed or enabled on shared hosts.
