"""Behavioural integration tests for the Divi shell (header.php/footer.php).

Every scenario executes the *real* theme templates and the *real* shared
renderers (`bioco_render_primary_navigation` / `bioco_render_site_footer` from
bioco-core) under PHP. Only WordPress runtime functions are stubbed; the
renderers themselves are never faked, so a broken template breaks the test.

Replaces the previous source-string checks in this file. Behaviour that lives
elsewhere is deliberately not duplicated here:

- the CHILD-THEME stylesheet enqueue (divi parent / child handles, order,
  deps, versioning): see
  test_wordpress_divi_home_styles.py::test_child_theme_enqueues_parent_then_child_stylesheet_depends_on_shared_shell.
  The bioco-core asset bootstrap is the one enqueue covered IN THIS FILE: its
  real `wp_enqueue_scripts`/`enqueue_block_editor_assets` hook registrations
  are captured and the registered callbacks executed
  (`test_bioco_core_enqueues_tokens_blocks_navigation_and_shared_shell_in_order`,
  `test_block_editor_assets_never_load_the_shell_chrome`).
- mobile-menu toggle and utility auto-hide *script* behaviour: see
  test_wordpress_navigation_scroll.py (executes the real bioco-navigation.js)

The full keep/replace/remove map of the WordPress test surface lives in
tests/README.md.
"""

import json
import subprocess
from functools import cache
from pathlib import Path

ROOT = Path(__file__).parents[1]
STAGING = "https://staging.example"

NAVIGATION_JSON = ROOT / "wordpress/web/app/mu-plugins/bioco-core/content/navigation.json"

_BODY_CLASS_ADDER = (
    "$GLOBALS['BIOCO_FILTERS']['body_class'][] = [10, static function (array $classes): array "
    "{ $classes[] = 'et_fixed_nav'; return $classes; }];\n"
)


def _php_source(
    current_page: str,
    singular: str,
    archive: str,
    template: str,
    extra_body_class_filter: bool,
) -> str:
    # fmt: off
    state = (
        "$GLOBALS['BIOCO_CURRENT_PAGE'] = '" + current_page + "';\n"
        + "$GLOBALS['BIOCO_SINGULAR'] = '" + singular + "';\n"
        + "$GLOBALS['BIOCO_ARCHIVE'] = '" + archive + "';\n"
        + "$GLOBALS['BIOCO_TEMPLATE'] = '" + template + "';\n"
    )
    return (
        "define('ABSPATH', __DIR__);\n"
        "$GLOBALS['BIOCO_FILTERS'] = [];\n"
        + state
        + "$GLOBALS['BIOCO_BASE_CLASSES'] = 'home page page-id-5 et_fixed_nav et_show_nav';\n"
        "function add_filter($hook, $callback, $priority = 10) {\n"
        "    $GLOBALS['BIOCO_FILTERS'][$hook][] = [(int) $priority, $callback];\n"
        "    return true;\n"
        "}\n"
        "function add_action($hook, $callback, $priority = 10) { return true; }\n"
        "function apply_filters($hook, $value) {\n"
        "    $hooks = $GLOBALS['BIOCO_FILTERS'][$hook] ?? [];\n"
        "    usort($hooks, static fn($a, $b) => $a[0] <=> $b[0]);\n"
        "    foreach ($hooks as [, $callback]) {\n"
        "        $value = $callback($value);\n"
        "    }\n"
        "    return $value;\n"
        "}\n"
        "function language_attributes() { echo 'lang=\"de-CH\"'; }\n"
        "function bloginfo($show) { echo 'UTF-8'; }\n"
        "function wp_head() { echo 'BIOCO-MARK-WP-HEAD'; }\n"
        "function wp_body_open() { echo 'BIOCO-MARK-WP-BODY-OPEN'; }\n"
        "function wp_footer() { echo 'BIOCO-MARK-WP-FOOTER'; }\n"
        "function body_class($extra = '') {\n"
        "    $classes = array_values(array_filter(explode(' ', trim($GLOBALS['BIOCO_BASE_CLASSES'] . ' ' . $extra))));\n"
        "    $classes = apply_filters('body_class', $classes);\n"
        "    echo 'class=\"' . esc_attr(implode(' ', array_unique($classes))) . '\"';\n"
        "}\n"
        "function is_page($page = '') {\n"
        "    return $GLOBALS['BIOCO_CURRENT_PAGE'] !== '' && (string) $page === $GLOBALS['BIOCO_CURRENT_PAGE'];\n"
        "}\n"
        "function is_singular($post_types = '') {\n"
        "    $singular = $GLOBALS['BIOCO_SINGULAR'];\n"
        "    if ($singular === '') return false;\n"
        "    if ($post_types === '' || $post_types === []) return true;\n"
        "    return in_array($singular, (array) $post_types, true);\n"
        "}\n"
        "function is_post_type_archive($post_types = '') {\n"
        "    $archive = $GLOBALS['BIOCO_ARCHIVE'];\n"
        "    if ($archive === '') return false;\n"
        "    if ($post_types === '' || $post_types === []) return true;\n"
        "    return in_array($archive, (array) $post_types, true);\n"
        "}\n"
        "function is_page_template($template = '') {\n"
        "    $current = $GLOBALS['BIOCO_TEMPLATE'];\n"
        "    if ($current === '') return false;\n"
        "    return (string) $template === $current;\n"
        "}\n"
        "function home_url($path = '/') { return 'https://staging.example' . $path; }\n"
        "function plugins_url($path = '', $plugin = null) {\n"
        "    // Mirrors WP: the URL base is the containing (mu-)plugin directory,\n"
        "    // located from the plugin file passed as second argument.\n"
        "    $tail = '';\n"
        "    if (is_string($plugin) && $plugin !== '') {\n"
        "        $dir = rtrim(str_replace('\\\\', '/', dirname($plugin)), '/');\n"
        "        $marker = '/mu-plugins/';\n"
        "        if (($pos = strpos($dir, $marker)) !== false) {\n"
        "            $tail = substr($dir, $pos + strlen($marker));\n"
        "        }\n"
        "    }\n"
        "    return 'https://staging.example/wp-content/mu-plugins/' . $tail . '/' . ltrim((string) $path, '/');\n"
        "}\n"
        "function esc_url($value) { return (string) $value; }\n"
        "function esc_attr($value) { return (string) $value; }\n"
        "function esc_html($value) { return (string) $value; }\n"
        "require 'wordpress/web/app/mu-plugins/bioco-core/includes/navigation.php';\n"
        "require 'wordpress/web/app/themes/bioco-divi/functions.php';\n"
        + (_BODY_CLASS_ADDER if extra_body_class_filter else "")
        + "ob_start();\n"
        "require 'wordpress/web/app/themes/bioco-divi/header.php';\n"
        "$header = ob_get_clean();\n"
        "ob_start();\n"
        "require 'wordpress/web/app/themes/bioco-divi/footer.php';\n"
        "$footer = ob_get_clean();\n"
        "echo json_encode(['header' => $header, 'footer' => $footer]);"
    )
    # fmt: on


@cache
def _render(
    current_page: str = "",
    singular: str = "",
    archive: str = "",
    template: str = "",
    extra_body_class_filter: bool = False,
) -> dict:
    result = subprocess.run(
        [
            "php",
            "-r",
            _php_source(current_page, singular, archive, template, extra_body_class_filter),
        ],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )
    return json.loads(result.stdout)


def _document(scenario: dict) -> str:
    return scenario["header"] + scenario["footer"]


# ---------------------------------------------------------------------------
# Contract data (the single navigation contract shared by both themes)
# ---------------------------------------------------------------------------


def _contract() -> dict:
    return json.loads(NAVIGATION_JSON.read_text())


def _expected_href(url: str) -> str:
    """Internal paths resolve against home_url(); absolute URLs pass through."""
    if url.startswith(("http://", "https://")):
        return url
    return STAGING + url


# ---------------------------------------------------------------------------
# Document structure and lifecycle hooks
# ---------------------------------------------------------------------------


def test_shell_renders_exactly_one_header_main_and_footer():
    scenario = _render()
    document = _document(scenario)

    assert document.count("<header ") == 1
    assert document.count("<main ") == 1
    assert document.count("<footer ") == 1
    assert document.count('id="page-container"') == 1
    assert document.count('id="bioco-main-content"') == 1

    assert '<header class="bioco-site-header">' in scenario["header"]
    assert '<div class="bioco-page-shell bioco-hero-nav-overlay">' in scenario["header"]
    assert "</main>" in scenario["footer"]


def test_shell_closes_every_element_it_opens_and_keeps_hooks_in_order():
    scenario = _render()
    document = _document(scenario)

    assert document.count("<main ") == document.count("</main>") == 1
    assert document.count("<header ") == document.count("</header>") == 1
    assert document.count("<footer ") == document.count("</footer>") == 1
    # The shell owns the page-container wrapper; the renderers own their own
    # nested divs. Both sides together must stay balanced.
    assert document.count("<div") == document.count("</div>")

    head = document.index("BIOCO-MARK-WP-HEAD")
    body_open = document.index("BIOCO-MARK-WP-BODY-OPEN")
    body = document.index("<body ")
    page_container = document.index('id="page-container"')
    header_open = document.index("<header ")
    header_close = document.index("</header>")
    main_content = document.index('id="bioco-main-content"')
    main_close = document.index("</main>")
    footer = document.index("<footer ")
    footer_close = document.index("</footer>")
    # page-container closes after the footer render, before wp_footer.
    page_container_close = document.index("</div>", footer_close)
    wp_footer = document.index("BIOCO-MARK-WP-FOOTER")
    body_close = document.index("</body>")

    assert head < body
    assert body < body_open
    assert body_open < page_container
    assert page_container < header_open
    assert header_open < header_close
    assert header_close < main_content
    assert main_content < main_close
    assert main_close < footer
    assert footer < footer_close
    assert footer_close < page_container_close
    assert page_container_close < wp_footer
    assert wp_footer < body_close

    assert document.rstrip().endswith("</html>")


def test_shell_marks_wordpress_hooks_instead_of_bypassing_them():
    scenario = _render()
    document = _document(scenario)

    for marker in ("BIOCO-MARK-WP-HEAD", "BIOCO-MARK-WP-BODY-OPEN", "BIOCO-MARK-WP-FOOTER"):
        assert document.count(marker) == 1, marker

    assert 'lang="de-CH"' in document
    assert "UTF-8" in document
    assert 'class="home page page-id-5"' in document


def test_blank_template_renders_content_without_header_or_footer():
    scenario = _render(template="page-template-blank.php")
    document = _document(scenario)

    assert "<header " not in document
    assert "<footer " not in document
    assert "bioco-site-header" not in document
    assert "bioco-site-footer" not in document

    assert document.count("<main ") == 1
    assert document.count('id="page-container"') == 1
    assert document.index("BIOCO-MARK-WP-HEAD") < document.index('id="bioco-main-content"')
    assert document.index('id="bioco-main-content"') < document.index("BIOCO-MARK-WP-FOOTER")
    assert document.rstrip().endswith("</html>")


# ---------------------------------------------------------------------------
# Body-class filtering (registered by functions.php, invoked by header.php)
# ---------------------------------------------------------------------------


def test_body_classes_lose_divi_fixed_nav_classes_through_the_registered_filter():
    scenario = _render()
    document = _document(scenario)

    assert 'class="home page page-id-5"' in document
    assert "et_fixed_nav" not in document
    assert "et_show_nav" not in document


def test_divi_fixed_nav_class_is_removed_even_when_a_lower_priority_filter_adds_it():
    scenario = _render(extra_body_class_filter=True)
    document = _document(scenario)

    assert "et_fixed_nav" not in document


# ---------------------------------------------------------------------------
# Primary navigation: canonical links, accessibility contract, current route
# ---------------------------------------------------------------------------


def test_primary_navigation_renders_the_full_canonical_contract():
    scenario = _render()
    document = _document(scenario)
    contract = _contract()

    # Two labelled navigation landmarks: utility above primary.
    assert '<nav class="bioco-utility-nav" aria-label="Hilfsnavigation">' in document
    assert '<nav class="bioco-primary-nav" aria-label="Hauptnavigation">' in document
    # Structure order: utility row, then primary row (logo first, then menu).
    utility = document.index('class="bioco-utility-nav"')
    primary = document.index('class="bioco-primary-nav"')
    logo = document.index('class="bioco-logo"')
    menu = document.index('id="bioco-primary-menu"')
    assert utility < primary
    assert primary < logo
    assert logo < menu

    for item in contract["utility"]:
        href = _expected_href(item["url"])
        assert f'href="{href}">{item["label"]}</a>' in document, item

    for item in contract["primary"]:
        href = _expected_href(item["url"])
        assert f'href="{href}">{item["label"]}</a>' in document, item

    cta = contract["cta"]
    assert f'class="bioco-primary-cta" href="{_expected_href(cta["url"])}">{cta["label"]}</a>' in document

    # The logo links home with the contract's alt text and the bioco-core
    # asset, resolved through the real plugins_url($path, $plugin) call.
    logo_src = "https://staging.example/wp-content/mu-plugins/bioco-core/assets/bioco-logo.png"
    assert f'href="{STAGING}/" aria-label="{contract["site"]["homeLabel"]}">' in document
    assert f'src="{logo_src}" alt="{contract["site"]["logoAlt"]}">' in document


def test_mobile_menu_contract_is_wired_for_accessible_script_control():
    scenario = _render()
    document = _document(scenario)
    site = _contract()["site"]

    toggle = (
        '<button class="bioco-menu-toggle" type="button" '
        f'aria-label="{site["menuOpenLabel"]}" '
        f'data-open-label="{site["menuOpenLabel"]}" '
        f'data-close-label="{site["menuCloseLabel"]}" '
        'aria-controls="bioco-primary-menu" aria-expanded="false">'
    )
    assert toggle in document
    assert document.count("bioco-menu-toggle") == 1
    # Exactly one menu element carries the id the toggle controls.
    assert document.count('id="bioco-primary-menu"') == 1
    assert 'aria-controls="bioco-primary-menu"' in document


def test_mobile_utility_entries_are_duplicated_into_the_primary_menu():
    scenario = _render()
    document = _document(scenario)
    utility = _contract()["utility"]

    menu_start = document.index('id="bioco-primary-menu"')
    menu_end = document.index("</ul>", menu_start)
    menu = document[menu_start:menu_end]

    for item in utility:
        href = _expected_href(item["url"])
        expected = (
            f'<li class="bioco-mobile-utility"><a href="{href}">{item["label"]}</a></li>'
        )
        assert expected in menu, item


def test_current_route_marks_the_matching_link_and_nothing_else():
    scenario = _render(current_page="gemuese")
    document = _document(scenario)

    assert '<li class="is-current"><a class="is-current" aria-current="page" href="https://staging.example/gemuese/">Gemüse</a></li>' in document
    assert document.count("aria-current") == 1
    assert document.count("is-current") == 2  # li + a


def test_event_pages_mark_aktuelles_as_current():
    singular = _render(singular="event")
    assert '<a class="is-current" aria-current="page" href="https://staging.example/aktuelles/">Aktuelles</a>' in singular["header"]
    assert singular["header"].count("aria-current") == 1

    archive = _render(archive="event")
    assert '<a class="is-current" aria-current="page" href="https://staging.example/aktuelles/">Aktuelles</a>' in archive["header"]
    assert archive["header"].count("aria-current") == 1


def test_utility_current_route_marks_both_the_row_and_the_mobile_copy():
    scenario = _render(current_page="kontakt")
    document = _document(scenario)

    assert document.count("aria-current") == 2  # utility row + mobile duplicate
    assert '<li class="is-current"><a class="is-current" aria-current="page" href="https://staging.example/kontakt/">Kontakt</a></li>' in document
    assert '<li class="bioco-mobile-utility is-current"><a class="is-current" aria-current="page" href="https://staging.example/kontakt/">Kontakt</a></li>' in document


def test_primary_navigation_without_a_current_route_marks_nothing():
    document = _document(_render())

    assert "aria-current" not in document
    assert "is-current" not in document


# ---------------------------------------------------------------------------
# Site footer: titles, canonical destinations, external link hardening
# ---------------------------------------------------------------------------


def test_site_footer_renders_the_approved_titles_and_internal_links():
    scenario = _render()
    footer = scenario["footer"]
    contract = _contract()["footer"]

    assert '<footer id="footer" class="bioco-site-footer">' in footer
    # Approved titles stay pinned here: the footer must render exactly these
    # German headings, not just whatever navigation.json currently says.
    assert "<h3>Navigation</h3>" in footer
    assert "<h3>Kontakt</h3>" in footer
    assert "<h3>Social Media</h3>" in footer
    assert "<h3>Partner & Zertifizierungen</h3>" in footer

    for item in contract["navigation"]:
        assert f'href="{_expected_href(item["url"])}">{item["label"]}</a>' in footer, item


def test_site_footer_renders_contact_details_with_mailto():
    footer = _render()["footer"]
    contract = _contract()["footer"]

    assert f"<strong>{contract['contactName']}</strong>" in footer
    for line in contract["contactAddress"]:
        assert line in footer
    assert "<br>" in footer
    assert f'href="mailto:{contract["contactEmail"]}">{contract["contactEmail"]}</a>' in footer


def test_site_footer_external_links_open_safely():
    footer = _render()["footer"]
    contract = _contract()["footer"]

    for item in contract["social"] + contract["partners"]:
        assert (
            f'<a href="{item["url"]}" target="_blank" rel="noopener noreferrer">'
            f'{item["label"]}</a>'
        ) in footer, item


def test_site_footer_renders_the_region_note():
    footer = _render()["footer"]
    assert _contract()["footer"]["regionText"] in footer


# ---------------------------------------------------------------------------
# bioco-core asset bootstrap (shared by the block theme and Divi)
# ---------------------------------------------------------------------------


def _run_core_enqueue_hook(hook_name: str) -> dict:
    """Require bioco-core.php and run every callback it registered on the
    given enqueue hook exactly like WordPress would — callbacks sorted by
    hook priority (stable, same priority keeps registration order), the
    assets must come from the hook registrations, not from calling the
    implementations."""
    php = (
        "define('ABSPATH', __DIR__);\n"
        "$GLOBALS['BIOCO_ENQUEUED'] = [];\n"
        "$GLOBALS['BIOCO_ACTIONS'] = [];\n"
        "function add_filter($hook, $callback, $priority = 10) { return true; }\n"
        "function add_action($hook, $callback, $priority = 10) {\n"
        "    $GLOBALS['BIOCO_ACTIONS'][$hook][] = [(int) $priority, $callback];\n"
        "    return true;\n"
        "}\n"
        "function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false) {\n"
        "    $GLOBALS['BIOCO_ENQUEUED'][] = ['type' => 'style', 'handle' => $handle, 'src' => $src, 'deps' => $deps, 'ver' => $ver];\n"
        "}\n"
        "function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $footer = false) {\n"
        "    $GLOBALS['BIOCO_ENQUEUED'][] = ['type' => 'script', 'handle' => $handle, 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => (bool) $footer];\n"
        "}\n"
        "function plugin_dir_url($file) { return 'https://staging.example/wp-content/mu-plugins/bioco-core/'; }\n"
        "require 'wordpress/web/app/mu-plugins/bioco-core/bioco-core.php';\n"
        "$hook_name = (string) $argv[1];\n"
        "$registered = array_map(\n"
        "    static fn(array $entry): array => [\n"
        "        'priority' => $entry[0],\n"
        "        'name' => is_string($entry[1])\n"
        "            ? $entry[1]\n"
        "            : get_class($entry[1]) . '::__invoke',\n"
        "        'callback' => $entry[1],\n"
        "    ],\n"
        "    $GLOBALS['BIOCO_ACTIONS'][$hook_name] ?? [],\n"
        ");\n"
        "uasort($registered, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);\n"
        "foreach ($registered as ['callback' => $callback]) {\n"
        "    call_user_func($callback);\n"
        "}\n"
        "foreach ($registered as &$entry) {\n"
        "    unset($entry['callback']);\n"
        "}\n"
        "echo json_encode(['registrations' => array_values($registered), 'enqueued' => $GLOBALS['BIOCO_ENQUEUED']]);"
    )
    result = subprocess.run(
        ["php", "-r", php, hook_name],
        cwd=ROOT,
        text=True,
        capture_output=True,
        check=True,
    )
    return json.loads(result.stdout)


def test_bioco_core_enqueues_tokens_blocks_navigation_and_shared_shell_in_order():
    front_end = _run_core_enqueue_hook("wp_enqueue_scripts")

    # Exactly two callbacks must be registered on wp_enqueue_scripts and they
    # alone must produce the assets: a missing or wrong hook registration
    # leaves the list empty or duplicated.
    # The shell must enqueue on a later hook priority than the theme
    # adapters' default-10 callbacks: the shell call runs after themes have
    # registered their base/child styles, and WordPress dependency resolution
    # (proven against the real WP_Dependencies in
    # test_wordpress_divi_home_styles.py) then prints it between them.
    assert front_end["registrations"] == [
        {"priority": 10, "name": "bioco_core_enqueue_block_assets"},
        {"priority": 20, "name": "bioco_core_enqueue_shell_style"},
    ]
    enqueued = front_end["enqueued"]

    handles = [asset["handle"] for asset in enqueued]
    assert handles == ["bioco-tokens", "bioco-blocks", "bioco-navigation", "bioco-shell"]

    tokens, blocks, navigation, shell = enqueued
    assert tokens["type"] == "style"
    assert tokens["deps"] == []
    assert tokens["src"].endswith("assets/bioco-tokens.css")

    assert blocks["deps"] == ["bioco-tokens"]
    assert blocks["src"].endswith("assets/bioco-blocks.css")

    assert navigation["type"] == "script"
    assert navigation["footer"] is True
    assert navigation["src"].endswith("assets/bioco-navigation.js")

    # The shared navigation/footer shell stylesheet (#180): owned by core,
    # loaded once for every theme, after the tokens it consumes.
    assert shell["type"] == "style"
    assert shell["deps"] == ["bioco-tokens"]
    assert shell["src"].endswith("assets/bioco-shell.css")

    for asset in enqueued:
        assert isinstance(asset["ver"], str) and asset["ver"].isdigit(), asset


def test_block_editor_assets_never_load_the_shell_chrome():
    """Editor canvas keeps tokens/blocks/navigation only.

    The shell stylesheet restyles <body> (cream background, overflow clip);
    enqueueing it under `enqueue_block_editor_assets` would recolor the
    wp-admin/editor body, so it must stay front-end-only.
    """
    editor = _run_core_enqueue_hook("enqueue_block_editor_assets")

    assert editor["registrations"] == [
        {"priority": 10, "name": "bioco_core_enqueue_block_assets"},
    ]
    handles = [asset["handle"] for asset in editor["enqueued"]]
    assert handles == ["bioco-tokens", "bioco-blocks", "bioco-navigation"]
    assert "bioco-shell" not in handles
