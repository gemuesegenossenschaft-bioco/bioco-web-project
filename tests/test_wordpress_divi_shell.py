from pathlib import Path


ROOT = Path(__file__).parents[1]
DIVI_THEME = ROOT / "wordpress/web/app/themes/bioco-divi"


def test_divi_uses_canonical_bioco_header_and_footer_renderers():
    header = (DIVI_THEME / "header.php").read_text()
    footer = (DIVI_THEME / "footer.php").read_text()

    assert header.count("bioco_render_primary_navigation()") == 1
    assert 'class="bioco-site-header"' in header
    assert 'class="bioco-page-shell bioco-hero-nav-overlay"' in header
    assert "main-header" not in header
    assert "show_page_menu" not in header

    assert footer.count("bioco_render_site_footer()") == 1
    assert "main-footer" not in footer
    assert "get_sidebar" not in footer

    navigation = __import__("json").loads((
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/content/navigation.json"
    ).read_text())
    footer_contract = navigation["footer"]
    assert footer_contract["navigationTitle"] == "Navigation"
    assert footer_contract["contactTitle"] == "Kontakt"
    assert footer_contract["socialTitle"] == "Social Media"
    assert footer_contract["partnersTitle"] == "Partner & Zertifizierungen"


def test_divi_reuses_shared_shell_styles_and_navigation_script():
    functions = (DIVI_THEME / "functions.php").read_text()

    assert "bioco-shell" in functions
    assert "$theme_root_uri . '/bioco/assets/app.css'" in functions
    assert "$theme_root_path . '/bioco/assets/app.css'" in functions
    assert "bioco-navigation" in (
        ROOT / "wordpress/web/app/mu-plugins/bioco-core/bioco-core.php"
    ).read_text()


def test_divi_shell_keeps_wordpress_lifecycle_hooks():
    header = (DIVI_THEME / "header.php").read_text()
    footer = (DIVI_THEME / "footer.php").read_text()

    assert "language_attributes()" in header
    assert "wp_head()" in header
    assert "body_class()" in header
    assert "wp_body_open()" in header
    assert "wp_footer()" in footer
    assert header.count('id="page-container"') == 1
    assert footer.count("</div>") >= 1


def test_divi_shell_wraps_parent_template_content_in_main():
    header = (DIVI_THEME / "header.php").read_text()
    footer = (DIVI_THEME / "footer.php").read_text()

    assert '<main id="bioco-main-content">' in header
    assert footer.count("</main>") == 1


def test_divi_disables_parent_fixed_header_offsets():
    functions = (DIVI_THEME / "functions.php").read_text()

    assert "body_class" in functions
    assert "et_fixed_nav" in functions
    assert "et_show_nav" in functions
    assert "array_values(array_diff" in functions
    assert "PHP_INT_MAX" in functions
