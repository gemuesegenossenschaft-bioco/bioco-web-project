"""
Pytest suite for the #134 Divi design-system checker contract.

The checker under test is::

    wordpress/scripts/check-divi-design-system.py

It accepts::

    --manifest PATH      JSON manifest to validate
    --require-exports    Fail if any export is missing / empty / placeholder
"""

import json
import subprocess
import sys
from pathlib import Path

import pytest

ROOT = Path(__file__).parents[1]
CHECKER = ROOT / "wordpress" / "scripts" / "check-divi-design-system.py"


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _run_checker(*args):
    """Invoke the checker script via subprocess; return CompletedProcess."""
    return subprocess.run(
        [sys.executable, str(CHECKER), *args],
        capture_output=True,
        text=True,
    )


@pytest.fixture
def tmp_manifest(tmp_path):
    """Return a helper that writes a temporary manifest dir and returns manifest path."""
    def _write(data: dict) -> Path:
        manifest_path = tmp_path / "manifest.json"
        manifest_path.write_text(json.dumps(data, indent=2))
        return manifest_path
    return _write


def _valid_base_manifest(manifest_dir: Path):
    """Return a dict for a fully valid base manifest."""
    css_file = manifest_dir / "styles.css"
    css_file.write_text(".btn { color: var(--bioco-green); }")

    exports_dir = manifest_dir / "exports"
    exports_dir.mkdir()

    tokens = {
        "colors": [{"title": "Bioco Green", "cssVar": "--bioco-green", "value": "#2e7d32"}],
        "fonts": [{"title": "DM Sans", "cssVar": "--font-dm-sans", "value": "'DM Sans', sans-serif"}],
        "typography": [{"title": "Heading 1", "cssVar": "--h1", "value": "2rem"}],
        "spacing": [{"title": "Small", "cssVar": "--space-sm", "value": "0.5rem"}],
        "radii": [{"title": "Default", "cssVar": "--radius", "value": "0.25rem"}],
        "borders": [{"title": "Thin", "cssVar": "--border-thin", "value": "1px solid"}],
        "shadows": [{"title": "Card", "cssVar": "--shadow-card", "value": "0 2px 4px rgba(0,0,0,0.1)"}],
    }

    option_group_presets = [{"title": "Primary Button", "groups": ["button", "primary"]}]
    element_presets = [{"title": "Hero Section", "element": "section"}]

    theme_builder = {
        "templates": [
            {"slot": "header", "title": "Global Header", "assignment": "default"},
            {"slot": "body", "title": "Global Body", "assignment": "default"},
            {"slot": "footer", "title": "Global Footer", "assignment": "default"},
        ]
    }

    exports = {
        "variables": str(exports_dir / "variables.json"),
        "presets": str(exports_dir / "presets.json"),
        "themeBuilder": str(exports_dir / "theme-builder.json"),
    }

    # Write export files with valid content
    token_titles = [t["title"] for group in tokens.values() for t in group]
    (exports_dir / "variables.json").write_text(json.dumps({"tokens": token_titles}))
    preset_titles = [p["title"] for p in option_group_presets + element_presets]
    (exports_dir / "presets.json").write_text(json.dumps({"presets": preset_titles}))
    template_titles = [t["title"] for t in theme_builder["templates"]]
    (exports_dir / "theme-builder.json").write_text(json.dumps({"templates": template_titles}))

    return {
        "schemaVersion": 1,
        "tokens": tokens,
        "optionGroupPresets": option_group_presets,
        "elementPresets": element_presets,
        "themeBuilder": theme_builder,
        "css": {"files": [str(css_file)], "exceptions": []},
        "exports": exports,
    }


def _manifest_with_css(tmp_path, manifest, css_text: str):
    """Overwrite the CSS file referenced by manifest with css_text."""
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(css_text)


def _out(result) -> str:
    """Combined stdout + stderr lowered."""
    return (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# Regression: declared var with nested rgba fallback passes
# ---------------------------------------------------------------------------


def test_declared_var_with_nested_rgba_fallback_passes(tmp_path, tmp_manifest):
    """var(--declared, rgba(...)) must pass because the variable is declared."""
    manifest = _valid_base_manifest(tmp_path)
    _manifest_with_css(tmp_path, manifest, ".btn { color: var(--bioco-green, rgba(0,0,0,0.5)); }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode == 0, result.stdout + result.stderr


# ---------------------------------------------------------------------------
# Regression: malformed nested manifest containers/items fail cleanly
# ---------------------------------------------------------------------------


@pytest.mark.parametrize(
    "mutate_func, expect_substring, require_exports",
    [
        # tokens list
        (lambda m: m.update({"tokens": "not-a-list"}), "list", False),
        # token item string
        (lambda m: m.update({"tokens": {"colors": ["not-a-dict"]}}), "non-blank", False),
        # themeBuilder list
        (lambda m: m.update({"themeBuilder": {"templates": "not-a-list"}}), "list", False),
        # template item string
        (lambda m: m.update({"themeBuilder": {"templates": ["not-a-dict"]}}), "non-blank", False),
        # css list
        (lambda m: m.update({"css": {"files": "not-a-list", "exceptions": []}}), "must be a list", False),
        # exception item string
        (lambda m: m.update({"css": {"files": [], "exceptions": ["not-a-dict"]}}), "non-blank", False),
        # exports list under --require-exports
        (lambda m: m.update({"exports": ["not-a-dict"]}), "missing", True),
    ],
    ids=[
        "tokens_list",
        "token_item_string",
        "themeBuilder_list",
        "template_item_string",
        "css_list",
        "exception_item_string",
        "exports_list",
    ],
)
def test_malformed_nested_manifest_fails_cleanly(tmp_path, tmp_manifest, mutate_func, expect_substring, require_exports):
    """Malformed nested structures must fail cleanly without traceback."""
    manifest = _valid_base_manifest(tmp_path)
    mutate_func(manifest)
    manifest_path = tmp_manifest(manifest)
    args = ["--manifest", str(manifest_path)]
    if require_exports:
        args.append("--require-exports")
    result = _run_checker(*args)
    assert result.returncode != 0
    out = _out(result)
    assert "traceback" not in out
    assert expect_substring in out


def test_top_level_themeBuilder_string_fails_cleanly(tmp_path, tmp_manifest):
    """A manifest where themeBuilder is a string must fail cleanly without traceback."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["themeBuilder"] = "not-a-dict"
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "traceback" not in out


def test_top_level_css_string_fails_cleanly(tmp_path, tmp_manifest):
    """A manifest where css is a string must fail cleanly without traceback."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["css"] = "not-a-dict"
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "traceback" not in out


def test_malformed_css_files_string_expects_list_message(tmp_path, tmp_manifest):
    """When css.files is a string, the error must mention 'css.files must be a list'."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["css"] = {"files": "not-a-list", "exceptions": []}
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = result.stdout + result.stderr
    assert "css.files must be a list" in out


def test_final_monitored_declaration_without_semicolon_fails(tmp_path, tmp_manifest):
    """A raw literal as the final declaration without a trailing semicolon must be caught."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".btn { color: #ff0000 }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = (result.stdout + result.stderr).lower()
    assert "literal" in out or "raw" in out or "token" in out


def test_mixed_box_shadow_with_declared_var_and_raw_literal_fails(tmp_path, tmp_manifest):
    """box-shadow mixing a declared var with a raw literal must fail."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".card { box-shadow: var(--shadow-card), 0 0 1px #000; }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = (result.stdout + result.stderr).lower()
    assert "literal" in out or "raw" in out or "token" in out or "non-token" in out


# ---------------------------------------------------------------------------
# Regression: duplicate cssVar across token groups fails
# ---------------------------------------------------------------------------


def test_duplicate_cssvar_across_token_groups_fails(tmp_path, tmp_manifest):
    """The same cssVar appearing in two token groups must fail."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["tokens"]["colors"].append(
        {"title": "Duplicate Green", "cssVar": "--bioco-green", "value": "#1a1a1a"}
    )
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "duplicate" in out or "cssvar" in out or "token" in out


# ---------------------------------------------------------------------------
# Regression: duplicate required theme slot fails
# ---------------------------------------------------------------------------


def test_duplicate_required_theme_slot_fails(tmp_path, tmp_manifest):
    """Two templates claiming the same required slot must fail."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["themeBuilder"]["templates"].append(
        {"slot": "header", "title": "Second Header", "assignment": "default"}
    )
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "duplicate" in out or "slot" in out


# ---------------------------------------------------------------------------
# Regression: empty css.files fails
# ---------------------------------------------------------------------------


def test_empty_css_files_fails(tmp_path, tmp_manifest):
    """An empty css.files list must fail."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["css"]["files"] = []
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "css" in out or "file" in out or "empty" in out


# ---------------------------------------------------------------------------
# Regression: unused documented CSS exception fails
# ---------------------------------------------------------------------------


def test_unused_documented_css_exception_fails(tmp_path, tmp_manifest):
    """An exception documented in the manifest but never used in CSS must fail."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".btn { color: var(--bioco-green); }")
    manifest["css"]["exceptions"] = [
        {"file": css_file.name, "property": "color", "value": "#ff0000", "reason": "legacy brand"}
    ]
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "unused" in out or "exception" in out


# ---------------------------------------------------------------------------
# Regression: exception file "style.css" must NOT match candidate "evilstyle.css"
# ---------------------------------------------------------------------------


def test_exception_file_must_not_substring_match_evilstyle(tmp_path, tmp_manifest):
    """An exception for 'style.css' must not erroneously match 'evilstyle.css'."""
    manifest = _valid_base_manifest(tmp_path)
    evil_css = tmp_path / "evilstyle.css"
    evil_css.write_text(".btn { color: #ff0000; }")
    manifest["css"]["files"] = [str(evil_css)]
    manifest["css"]["exceptions"] = [
        {"file": "style.css", "property": "color", "value": "#ff0000", "reason": "allowed"}
    ]
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = _out(result)
    assert "literal" in out or "raw" in out or "token" in out or "non-token" in out


# ---------------------------------------------------------------------------
# Regression: monitored raw declaration inside CSS comment is ignored/passes
# ---------------------------------------------------------------------------


def test_monitored_raw_declaration_inside_css_comment_ignored(tmp_path, tmp_manifest):
    """A raw literal inside a CSS comment must not trigger a failure."""
    manifest = _valid_base_manifest(tmp_path)
    _manifest_with_css(
        tmp_path,
        manifest,
        "/* .btn { color: #ff0000; } */\n.btn { color: var(--bioco-green); }",
    )
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode == 0, result.stdout + result.stderr


# ---------------------------------------------------------------------------
# Baseline contract
# ---------------------------------------------------------------------------


def test_default_real_contract_passes_eventually():
    """The real project manifest must satisfy the checker with no flags."""
    result = _run_checker()
    assert result.returncode == 0, result.stdout + result.stderr


# ---------------------------------------------------------------------------
# Valid temp manifest
# ---------------------------------------------------------------------------


def test_complete_temp_exports_pass(tmp_path, tmp_manifest):
    """A fully populated temporary manifest with valid exports must pass."""
    manifest = _valid_base_manifest(tmp_path)
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode == 0, result.stdout + result.stderr


# ---------------------------------------------------------------------------
# Undefined token
# ---------------------------------------------------------------------------


def test_undefined_token_css_var_fails(tmp_path, tmp_manifest):
    """CSS using a token variable not declared in tokens must fail."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".btn { color: var(--undeclared-var); }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    assert "token" in (result.stdout + result.stderr).lower() or "undefined" in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# Raw one-off literal
# ---------------------------------------------------------------------------


def test_raw_one_off_color_literal_fails(tmp_path, tmp_manifest):
    """A raw CSS colour literal outside the token system must fail."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".btn { color: #ff0000; }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    assert "literal" in (result.stdout + result.stderr).lower() or "raw" in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# Missing exception reason
# ---------------------------------------------------------------------------


def test_missing_exception_reason_fails(tmp_path, tmp_manifest):
    """Any CSS exception entry without a 'reason' field must fail."""
    manifest = _valid_base_manifest(tmp_path)
    manifest["css"]["exceptions"] = [{"file": "styles.css", "property": "color", "value": "#ff0000"}]
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    assert "reason" in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# --require-exports
# ---------------------------------------------------------------------------


def test_require_exports_missing_file_fails(tmp_path, tmp_manifest):
    """With --require-exports, a missing export file must fail."""
    manifest = _valid_base_manifest(tmp_path)
    exports_dir = tmp_path / "exports"
    exports_dir.mkdir(exist_ok=True)
    manifest["exports"]["variables"] = str(exports_dir / "nonexistent-variables.json")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode != 0
    assert "export" in (result.stdout + result.stderr).lower()


def test_require_exports_empty_object_file_fails(tmp_path, tmp_manifest):
    """With --require-exports, an export file containing only {} must fail."""
    manifest = _valid_base_manifest(tmp_path)
    exports_dir = tmp_path / "exports"
    exports_dir.mkdir(exist_ok=True)
    empty_file = exports_dir / "empty.json"
    empty_file.write_text("{}")
    manifest["exports"]["variables"] = str(empty_file)
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode != 0
    assert "export" in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# Missing preset title in exports
# ---------------------------------------------------------------------------


def test_missing_option_preset_title_in_presets_export_fails(tmp_path, tmp_manifest):
    """If presets.json export omits an optionGroupPreset title, the checker must fail."""
    manifest = _valid_base_manifest(tmp_path)
    exports_dir = tmp_path / "exports"
    exports_dir.mkdir(exist_ok=True)
    # Omit the option group preset title
    (exports_dir / "presets.json").write_text(json.dumps({"presets": ["Hero Section"]}))
    manifest["exports"]["presets"] = str(exports_dir / "presets.json")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode != 0
    assert "preset" in (result.stdout + result.stderr).lower() or "title" in (result.stdout + result.stderr).lower()


def test_missing_element_preset_title_in_presets_export_fails(tmp_path, tmp_manifest):
    """If presets.json export omits an elementPreset title, the checker must fail."""
    manifest = _valid_base_manifest(tmp_path)
    exports_dir = tmp_path / "exports"
    exports_dir.mkdir(exist_ok=True)
    # Omit the element preset title
    (exports_dir / "presets.json").write_text(json.dumps({"presets": ["Primary Button"]}))
    manifest["exports"]["presets"] = str(exports_dir / "presets.json")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode != 0
    assert "preset" in (result.stdout + result.stderr).lower() or "title" in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# Parametrized missing template title in theme-builder export
# ---------------------------------------------------------------------------


@pytest.mark.parametrize("slot", ["header", "body", "footer"])
def test_missing_template_title_in_theme_builder_export_fails(tmp_path, tmp_manifest, slot):
    """If theme-builder.json export omits a template title, the checker must fail."""
    manifest = _valid_base_manifest(tmp_path)
    exports_dir = tmp_path / "exports"
    exports_dir.mkdir(exist_ok=True)
    # Build template titles excluding the one under test
    all_slots = ["header", "body", "footer"]
    titles = []
    for s in all_slots:
        if s != slot:
            titles.append(f"Global {s.capitalize()}")
    (exports_dir / "theme-builder.json").write_text(json.dumps({"templates": titles}))
    manifest["exports"]["themeBuilder"] = str(exports_dir / "theme-builder.json")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode != 0
    assert "title" in (result.stdout + result.stderr).lower() or "template" in (result.stdout + result.stderr).lower() or slot in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# var() with fallback
# ---------------------------------------------------------------------------


def test_var_with_declared_fallback_passes(tmp_path, tmp_manifest):
    """var(--declared) without fallback must pass because the variable is declared."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".btn { color: var(--bioco-green); }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode == 0, result.stdout + result.stderr


def test_var_with_undefined_fallback_fails(tmp_path, tmp_manifest):
    """var(--undefined, #fff) must fail because the variable is not declared."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(".btn { color: var(--undefined-var, #fff); }")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    assert "undefined" in (result.stdout + result.stderr).lower()


# ---------------------------------------------------------------------------
# Parameterized raw monitored one-offs
# ---------------------------------------------------------------------------


@pytest.mark.parametrize(
    "css_snippet",
    [
        "background-color: #ff0000;",
        "border-color: rgb(0,0,0);",
        "fill: hsl(0,0%,0%);",
        "stroke: rgba(0,0,0,0.5);",
    ],
)
def test_raw_monitored_one_off_fails(tmp_path, tmp_manifest, css_snippet):
    """Raw literals for monitored color-related properties must fail."""
    manifest = _valid_base_manifest(tmp_path)
    css_file = Path(manifest["css"]["files"][0])
    css_file.write_text(f".btn {{ {css_snippet} }}")
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = (result.stdout + result.stderr).lower()
    assert "literal" in out or "raw" in out or "undefined" in out or "token" in out


# ---------------------------------------------------------------------------
# Parameterized nested export marker failure
# ---------------------------------------------------------------------------


@pytest.mark.parametrize("marker", ["placeholder", "synthetic", "fabricated"])
def test_require_exports_nested_marker_fails(tmp_path, tmp_manifest, marker):
    """With --require-exports, nested marker strings in exports must fail."""
    manifest = _valid_base_manifest(tmp_path)
    exports_dir = tmp_path / "exports"
    exports_dir.mkdir(exist_ok=True)
    bad_file = exports_dir / "variables.json"
    bad_file.write_text(json.dumps({"tokens": [f"__{marker}__"]}))
    manifest["exports"]["variables"] = str(bad_file)
    manifest_path = tmp_manifest(manifest)
    result = _run_checker("--manifest", str(manifest_path), "--require-exports")
    assert result.returncode != 0
    out = (result.stdout + result.stderr).lower()
    assert "export" in out or marker in out


# ---------------------------------------------------------------------------
# Invalid JSON manifest
# ---------------------------------------------------------------------------


def test_invalid_json_manifest_fails_cleanly(tmp_path, tmp_manifest):
    """A manifest that is not valid JSON must fail with a clean error."""
    manifest_path = tmp_path / "manifest.json"
    manifest_path.write_text("{ not json }")
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = result.stdout + result.stderr
    assert "json" in out.lower()


# ---------------------------------------------------------------------------
# Top-level JSON array manifest
# ---------------------------------------------------------------------------


def test_top_level_array_manifest_fails_cleanly(tmp_path, tmp_manifest):
    """A manifest whose top level is a JSON array must fail cleanly."""
    manifest_path = tmp_path / "manifest.json"
    manifest_path.write_text(json.dumps(["invalid", "array"]))
    result = _run_checker("--manifest", str(manifest_path))
    assert result.returncode != 0
    out = result.stdout + result.stderr
    # Should fail on schemaVersion check or similar structural issue
    assert "schemaversion" in out.lower() or "object" in out.lower() or "json" in out.lower()
