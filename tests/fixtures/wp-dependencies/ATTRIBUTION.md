# Attribution: WordPress dependencies test fixture

`WP_Dependencies` and `_WP_Dependency` are WordPress core classes; the enqueue
contracts resolve stylesheet order with them. The suite must exercise the REAL
WordPress dependency resolution, not a second in-repo reimplementation. These
files are copied **byte-for-byte unchanged** from WordPress core so tests run
against the genuine resolution algorithm (queue-order DFS over `deps`,
`all_deps()`/`recurse_deps()` semantics).

| File | SHA-256 |
| --- | --- |
| `class-wp-dependencies.php` | `1a7e9f3ae68ac987d1979b41eee800740ede85d0f7431fb4ec0a1b58f7b65051` |
| `class-wp-dependency.php` | `41cbd8c13705edc829caf39fcbb55e3ebf5ae0338a82fe6320f30c2a361a93f7` |
| `COPYING.txt` | `49e97f629ce763abb2f8f1f13da74cb5a8a9a72a0e72a7477d1540c98cef5141` |

## Upstream source and version

- **Version:** WordPress 7.1.0 (`$wp_version = '7.1'`, `wp-includes/version.php`).
- **Upstream paths:** `wp-includes/class-wp-dependencies.php`,
  `wp-includes/class-wp-dependency.php`.
- **Upstream license file:** `license.txt` in the WordPress root, copied here
  as `COPYING.txt` (GNU General Public License v2 or later, copyright by the
  WordPress contributors).
- **License text URL:** <https://wordpress.org/about/license/> and the
  canonical GPLv2 text at <https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt>.
- **Source repository:** <https://github.com/WordPress/wordpress-develop>
  (`src/wp-includes/class-wp-dependencies.php`,
  `src/wp-includes/class-wp-dependency.php`).
- **Read from:** container `bioco-divi-runtime-wp` (local WordPress 7.1 test
  runtime), September 2026.

## What is NOT vendored

Only the two dependency classes are vendored. The harness in
`tests/test_wordpress_divi_home_styles.py` stubs the WordPress functions the
classes may call on warning paths only (`_doing_it_wrong()`, `__()`,
`wp_get_list_item_separator()`); no resolution behaviour is reimplemented.
`WP_Styles::do_item()` output/URL logic is out of scope: stylesheet URL
emission is proven against the real WordPress runtime instead.
