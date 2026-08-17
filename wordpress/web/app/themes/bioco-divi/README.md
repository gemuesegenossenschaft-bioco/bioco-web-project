# bioco Divi Child

Thin Divi 5 child theme (#101). Homepage layout styling and asset bootstrap only — no content,
blocks, or forms live here; those come from the theme-agnostic mu-plugins (`bioco-core`,
`bioco-content`, `bioco-forms`).

## Divi itself is not in this repo

**Divi is a licensed, commercial product.** It must be installed separately, on the server, by
whoever holds the license:

1. Download the Divi theme .zip from the Elegant Themes / Divi account that holds the license.
2. In `wp-admin`, go to **Themes > Add New > Upload Theme** and upload the .zip. (Or place it at
   `web/app/themes/Divi/` via a licensed deploy pipeline outside this repo.)
3. Activate `bioco Divi Child` (this theme, `Template: Divi`) — **not** the parent Divi theme
   directly, so `wp-content` customizations here (parent stylesheet enqueue) apply.
4. Assign pages/templates in the Divi Theme Builder as needed.

`web/app/themes/Divi` is listed in the root `.gitignore` for exactly this reason: it must never be
committed to this **public** repo. Never commit secrets, tokens, or passwords. A licensed theme .zip
is not a secret in the credential sense, but it is
someone else's commercial software and redistributing it here would violate the Divi license.
