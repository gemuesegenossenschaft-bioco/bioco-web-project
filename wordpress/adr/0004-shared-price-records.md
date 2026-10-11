# Shared price records

## Design target

Table rows and calculator choices must read the same editor-owned amounts. Divi owns labels, arrangement, typography and responsive settings. Membership receives stable calculator keys and an explicit existing backend Abo type.

## Approach A

Keep the calculator module as the canonical source. `bioco_price_catalog(page_id)` reads its saved attrs; table bindings look up those values.

Caller: `bioco_price_catalog($calculator_page)`.

This reuses existing fields but ties every price reader to one presentation module and cannot distinguish a removed module from an empty price list.

## Approach B

Store tiers as WordPress price records and the single share price in WordPress settings. `bioco_price_catalog()` returns typed published tiers and the share price after explicit activation. `bioco_price_abo_type(key)` resolves membership's existing enum.

Caller: `$catalog = bioco_price_catalog();`.

This keeps stable record keys while display names change. Native record loops can expand when a tier is added. It requires a separate initial adoption and a settings screen for the share price.

## Approach C

Store one price sheet with an unlimited ACF repeater. `bioco_price_catalog(sheet_id)` reads its rows.

Caller: `bioco_price_catalog($sheet)`.

All prices fit one screen, but repeater rows are not individual WordPress records for native Divi loops. A second projection renderer would need to duplicate those rows.

## Chosen design and depth

Choose B. One read interface handles both price consumers, explicit empty catalogs, zero values and stable membership keys. The extra settings screen is a disadvantage compared with C. Record loops fit the existing native Divi binding direction better than a second repeater renderer.

## Implementation steps

1. Add the editor-owned price record and share-price settings. Read legacy module values until the catalog is explicitly activated.
2. Validate a private adoption plan against the currently saved table and calculator values. Refuse divergence; preserve each current calculator slug and backend type.
3. Bind native table and calculator layouts to that source. Do not remove legacy rendering until native save/reopen and production behavior pass.

Steps 2 and 3 require their own implementation and release evidence. This design record does not establish those capabilities.
