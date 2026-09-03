from pathlib import Path
import json
import shutil
import subprocess


ROOT = Path(__file__).resolve().parents[1]
GATE = ROOT / "wordpress/scripts/check-hardcoded-content.php"
SEED_GATE = ROOT / "wordpress/scripts/check-seed-plan.php"
WP_CONTENT = ROOT / "wordpress/content-seed/block-content/defaults.json"
CMS_CONTENT = ROOT / "cms/content-seed/block-content/defaults.json"


def test_hardcoded_content_gate_checks_acf_json_without_opt_in():
    result = subprocess.run(
        ["php", str(GATE)],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=False,
    )

    assert result.returncode == 0, result.stdout + result.stderr
    assert "acf-json wurde NICHT geprueft" not in result.stdout
    assert "kein redaktioneller ACF-Default" in result.stdout


def test_seed_plan_gate_validates_shared_block_content():
    result = subprocess.run(
        ["php", str(SEED_GATE)],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=False,
    )

    assert result.returncode == 0, result.stdout + result.stderr
    assert "Block-Inhaltsseeds: 15" in result.stdout
    assert "doi-confirm" in result.stdout
    assert "event-signup-form" in result.stdout
    assert "gallery" in result.stdout

    used_blocks = result.stdout.split("Verwendete Bloecke:", 1)[1]
    for block in ("doi-confirm", "event-signup-form", "gallery"):
        assert f"  {block}" in used_blocks


def test_block_content_seed_preserves_all_migrated_fields():
    document = json.loads(WP_CONTENT.read_text())

    assert document["version"] == 1
    assert len(document["blocks"]) == 15
    # 131 migrierte Felder + 17 neue editierbare Formular-Meldungen (Issue #158)
    assert sum(len(values) for values in document["blocks"].values()) == 148
    assert document["blocks"]["pricing-calculator"]["signup_url"] == "/anmeldung"
    assert document["blocks"]["doi-confirm"]["success_title"] == "Anmeldung bestätigt"
    assert document["blocks"]["event-signup-form"]["event_title_prefix"] == "Anmeldung für:"
    assert document["blocks"]["gallery"]["filters"][0] == {"key": "all", "label": "Alles"}
    assert document["blocks"]["membership-form"]["depots"][0] == {"option": "Depot Chrättli"}


def test_content_seed_trees_are_mirrored_byte_for_byte():
    wordpress_files = {
        path.relative_to(ROOT / "wordpress/content-seed")
        for path in (ROOT / "wordpress/content-seed").rglob("*")
        if path.is_file()
    }
    cms_files = {
        path.relative_to(ROOT / "cms/content-seed")
        for path in (ROOT / "cms/content-seed").rglob("*")
        if path.is_file()
    }

    assert wordpress_files == cms_files
    for relative_path in wordpress_files:
        assert (ROOT / "wordpress/content-seed" / relative_path).read_bytes() == (
            ROOT / "cms/content-seed" / relative_path
        ).read_bytes()


def test_importer_has_no_legacy_acf_serialization_path():
    importer = ROOT / "wordpress/web/app/mu-plugins/bioco-import"
    php = "\n".join(path.read_text() for path in importer.rglob("*.php"))

    assert not (importer / "includes/acf-fields.php").exists()
    assert "bioco_import_serialize_acf_block" not in php
    assert "bioco_import_acf_block_data" not in php
    assert "assert_acf_available" not in php
    assert "acf_group" not in (importer / "includes/section-map.php").read_text()


def test_hardcoded_content_gate_rejects_a_new_acf_editorial_default(tmp_path):
    copied_wordpress = tmp_path / "wordpress"
    shutil.copytree(ROOT / "wordpress", copied_wordpress)
    group_path = copied_wordpress / (
        "web/app/mu-plugins/bioco-core/acf-json/group_bioco_block_contact_form.json"
    )
    group = json.loads(group_path.read_text())
    phone_label = next(field for field in group["fields"] if field.get("name") == "phone_label")
    phone_label["default_value"] = "Neue redaktionelle Vorgabe"
    group_path.write_text(json.dumps(group, ensure_ascii=False, indent=4) + "\n")

    result = subprocess.run(
        ["php", str(copied_wordpress / "scripts/check-hardcoded-content.php")],
        cwd=tmp_path,
        capture_output=True,
        text=True,
        check=False,
    )

    assert result.returncode == 1
    assert "acf-default-content" in result.stdout

    details = subprocess.run(
        ["php", str(copied_wordpress / "scripts/check-hardcoded-content.php"), "--list"],
        cwd=tmp_path,
        capture_output=True,
        text=True,
        check=False,
    )
    assert "Neue redaktionelle Vorgabe" in details.stdout
