# Attribution: WordPress block parser test fixture

`parse_blocks()` and `WP_Block_Parser` are WordPress core functions/classes;
the bioco importer's runtime verification calls them. The suite must exercise
the REAL WordPress block parser, not a second in-repo implementation. These
files are copied **byte-for-byte unchanged** from WordPress core so tests run
against the genuine parser.

| File | SHA-256 |
| --- | --- |
| `class-wp-block-parser.php` | `49fa9b16292e4407c591f292f2515c007b0784c594dd978212ee5faccb499420` |
| `class-wp-block-parser-block.php` | `7e21f483cf0c9deddd2144e026842bf906c72983908f7bd163c0716e8f6f325e` |
| `class-wp-block-parser-frame.php` | `3a66c88fa2397a842884908d83d1d03e5cb9cfb23fbb6d3cdf573bcd1520f0ae` |
| `COPYING.txt` | `49e97f629ce763abb2f8f1f13da74cb5a8a9a72a0e72a7477d1540c98cef5141` |

## Upstream source and version

- **Version:** WordPress 7.1.0 (`$wp_version = '7.1'`, `wp-includes/version.php`).
- **Upstream paths:** `wp-includes/class-wp-block-parser.php`,
  `wp-includes/class-wp-block-parser-block.php`,
  `wp-includes/class-wp-block-parser-frame.php`.
- **Upstream license file:** `license.txt` in the WordPress root, copied here
  as `COPYING.txt` (GNU General Public License v2 or later, copyright by the
  WordPress contributors).
- **License text URL:** <https://wordpress.org/about/license/> and the
  canonical GPLv2 text at <https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt>.
- **Source repository:** <https://github.com/WordPress/wordpress-develop>
  (`src/wp-includes/class-wp-block-parser*.php`).
- **Read from:** container `bioco-divi-runtime-wp` (local Divi editor runtime:
  WordPress 7.1 + Secure Custom Fields 6.9.5 + Divi 5.11; see
  `/tmp/bioco-goni-review/native-runtime-report.md`), copied 2026-09-13.
- **Accuracy note:** the parser files themselves do NOT carry per-file GPL
  headers — they carry WordPress `@package` doc blocks only. The GPLv2 (or
  later) license applies to WordPress core as a whole via the root
  `license.txt` (included here as `COPYING.txt`). This note describes ONLY
  the upstream licensing of these fixture files; it makes no claim about
  the licensing of this repository as a whole.
- **Update procedure:** refresh the files from the same path in a newer
  WordPress checkout, recompute the SHA-256 hashes, bump the version here.
  Never edit the parser sources themselves.

The licensed Divi parent theme is NOT vendored here (or anywhere in the
repo); only these three GPL WordPress core parser files plus their license
text are committed.
