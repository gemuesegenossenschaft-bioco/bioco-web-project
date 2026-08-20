#!/usr/bin/env python3
"""Divi design-system checker for contract #134."""

import argparse
import json
import re
import sys
from pathlib import Path


def _error(msg: str, errors: list):
    errors.append(msg)


def _nonblank(value) -> bool:
    return isinstance(value, str) and value.strip() != ""


def _has_placeholder(obj) -> bool:
    """Recursively check for placeholder/synthetic/fabricated markers (case-insensitive)."""
    pattern = re.compile(r"placeholder|synthetic|fabricated", re.IGNORECASE)
    if isinstance(obj, dict):
        for k, v in obj.items():
            if pattern.search(str(k)):
                return True
            if _has_placeholder(v):
                return True
    elif isinstance(obj, list):
        for item in obj:
            if _has_placeholder(item):
                return True
    elif isinstance(obj, str):
        if pattern.search(obj):
            return True
    return False


def _collect_strings(obj) -> list:
    """Recursively collect all string values."""
    result = []
    if isinstance(obj, dict):
        for v in obj.values():
            result.extend(_collect_strings(v))
    elif isinstance(obj, list):
        for item in obj:
            result.extend(_collect_strings(item))
    elif isinstance(obj, str):
        result.append(obj)
    return result


def _resolve_path(path_str: str, base_dir: Path) -> Path:
    p = Path(path_str)
    if p.is_absolute():
        return p
    return base_dir / p


def _strip_css_comments(content: str) -> str:
    """Remove /* ... */ comments from CSS."""
    return re.sub(r"/\*.*?\*/", "", content, flags=re.DOTALL)


def _extract_var_refs(raw_value: str):
    """Extract CSS variable names from var() references, handling nested fallbacks."""
    names = []
    i = 0
    while i < len(raw_value):
        idx = raw_value.find("var(", i)
        if idx == -1:
            break
        # find matching closing paren, accounting for nesting
        start = idx + 4
        depth = 1
        j = start
        while j < len(raw_value) and depth > 0:
            if raw_value[j] == "(":
                depth += 1
            elif raw_value[j] == ")":
                depth -= 1
            j += 1
        inner = raw_value[start:j - 1]
        # The variable name is the first comma-free token, trimmed
        comma = inner.find(",")
        if comma == -1:
            name = inner.strip()
        else:
            name = inner[:comma].strip()
        if name.startswith("--"):
            names.append(name)
        i = j
    return names


def _strip_css_blocks(content: str) -> str:
    """Remove @font-face { ... } blocks from CSS (non-nested)."""
    return re.sub(r"@font-face\s*\{[^}]*\}", "", content, flags=re.IGNORECASE)


def _remove_var_functions(value: str) -> str:
    """Remove full balanced var(...) functions (including fallbacks) from a value."""
    result = []
    i = 0
    while i < len(value):
        idx = value.find("var(", i)
        if idx == -1:
            result.append(value[i:])
            break
        result.append(value[i:idx])
        start = idx + 4
        depth = 1
        j = start
        while j < len(value) and depth > 0:
            if value[j] == "(":
                depth += 1
            elif value[j] == ")":
                depth -= 1
            j += 1
        i = j
    return "".join(result)


def _meaningful_remainder(value: str) -> str:
    """Return value with var()s stripped, whitespace collapsed, and !important removed."""
    remainder = _remove_var_functions(value)
    remainder = re.sub(r"\s+", " ", remainder).strip()
    remainder = re.sub(r"\s*!important\s*$", "", remainder, flags=re.IGNORECASE)
    return remainder.strip()


def main() -> int:
    script_dir = Path(__file__).resolve().parent
    repo_root = script_dir.parents[1]
    default_manifest = script_dir.parent / "design-system" / "v1" / "manifest.json"

    parser = argparse.ArgumentParser(description="Check Divi design-system manifest.")
    parser.add_argument(
        "--manifest",
        type=str,
        default=str(default_manifest),
        help="Path to manifest JSON",
    )
    parser.add_argument(
        "--require-exports",
        action="store_true",
        help="Require valid, non-empty, non-placeholder export files",
    )
    args = parser.parse_args()

    manifest_path = Path(args.manifest).resolve()
    errors = []

    if not manifest_path.is_file():
        print(f"FAIL: manifest not found: {manifest_path}")
        return 1

    try:
        with manifest_path.open("r", encoding="utf-8") as f:
            data = json.load(f)
    except (json.JSONDecodeError, OSError) as exc:
        print(f"FAIL: invalid JSON in manifest: {exc}")
        return 1

    if not isinstance(data, dict):
        print("FAIL: manifest must be a JSON object")
        return 1

    # schemaVersion
    if data.get("schemaVersion") != 1:
        _error("schemaVersion must be 1", errors)

    # tokens: 7 groups, each nonempty, each token has title/cssVar/value nonblank
    token_groups = ["colors", "fonts", "typography", "spacing", "radii", "borders", "shadows"]
    tokens = data.get("tokens", {})
    all_token_titles = []
    declared_var_names = set()
    seen_cssvars = set()

    if not isinstance(tokens, dict):
        _error("tokens must be a dict, not a list", errors)
    else:
        for group in token_groups:
            grp = tokens.get(group)
            if not isinstance(grp, list) or len(grp) == 0:
                _error(f"token group '{group}' must be a non-empty list", errors)
                continue
            for idx, token in enumerate(grp):
                if not isinstance(token, dict):
                    _error(f"token {group}[{idx}] must be a dict with non-blank title/cssVar/value", errors)
                    continue
                for field in ("title", "cssVar", "value"):
                    if not _nonblank(token.get(field)):
                        _error(f"token {group}[{idx}].{field} must be non-blank", errors)
                if _nonblank(token.get("title")):
                    all_token_titles.append(token["title"])
                if _nonblank(token.get("cssVar")):
                    cv = token["cssVar"].strip()
                    if cv in seen_cssvars:
                        _error(f"duplicate cssVar '{cv}' across token groups", errors)
                    seen_cssvars.add(cv)
                    declared_var_names.add(cv)

    # optionGroupPresets and elementPresets: nonempty lists, required fields
    option_presets = data.get("optionGroupPresets", [])
    element_presets = data.get("elementPresets", [])
    all_preset_titles = []

    if not isinstance(option_presets, list) or len(option_presets) == 0:
        _error("optionGroupPresets must be a non-empty list", errors)
    else:
        for idx, p in enumerate(option_presets):
            if not isinstance(p, dict):
                _error(f"optionGroupPresets[{idx}] must be a dict", errors)
                continue
            if not _nonblank(p.get("title")):
                _error(f"optionGroupPresets[{idx}].title must be non-blank", errors)
            else:
                all_preset_titles.append(p["title"])
            groups = p.get("groups")
            if not isinstance(groups, list) or len(groups) == 0:
                _error(f"optionGroupPresets[{idx}].groups must be a non-empty list", errors)

    if not isinstance(element_presets, list) or len(element_presets) == 0:
        _error("elementPresets must be a non-empty list", errors)
    else:
        for idx, p in enumerate(element_presets):
            if not isinstance(p, dict):
                _error(f"elementPresets[{idx}] must be a dict", errors)
                continue
            if not _nonblank(p.get("title")):
                _error(f"elementPresets[{idx}].title must be non-blank", errors)
            else:
                all_preset_titles.append(p["title"])
            if not _nonblank(p.get("element")):
                _error(f"elementPresets[{idx}].element must be non-blank", errors)

    # themeBuilder: exactly header, body, footer templates with nonblank title and assignment default
    theme_builder = data.get("themeBuilder", {})
    if not isinstance(theme_builder, dict):
        _error("themeBuilder must be a dict", errors)
        templates = []
    else:
        templates = theme_builder.get("templates", [])
    all_template_titles = []
    required_slots = {"header", "body", "footer"}
    seen_slots = set()

    if not isinstance(templates, list):
        _error("themeBuilder.templates must be a list", errors)
    else:
        for idx, t in enumerate(templates):
            if not isinstance(t, dict):
                _error(f"themeBuilder.templates[{idx}] must be a dict with non-blank title and assignment", errors)
                continue
            slot = t.get("slot")
            title = t.get("title", "")
            assignment = t.get("assignment", "")
            if slot in required_slots:
                if slot in seen_slots:
                    _error(f"duplicate themeBuilder slot '{slot}'", errors)
                seen_slots.add(slot)
            if not _nonblank(title):
                _error(f"themeBuilder.templates[{idx}].title must be non-blank", errors)
            else:
                all_template_titles.append(title)
            if assignment != "default":
                _error(f"themeBuilder.templates[{idx}].assignment must be 'default'", errors)
        missing = required_slots - seen_slots
        for slot in missing:
            _error(f"themeBuilder.templates missing required slot '{slot}'", errors)

    # CSS files and exceptions
    css = data.get("css", {})
    if not isinstance(css, dict):
        _error("css must be a dict", errors)
        css_files = []
        exceptions = []
    else:
        css_files = css.get("files", [])
        exceptions = css.get("exceptions", [])
        if not isinstance(css_files, list):
            _error("css.files must be a list", errors)
            css_files = []
        elif len(css_files) == 0:
            _error("css.files must be non-empty", errors)

        if not isinstance(exceptions, list):
            _error("css.exceptions must be a list", errors)
            exceptions = []

    # Validate exception entries
    declared_exceptions = []
    for idx, exc in enumerate(exceptions):
        if not isinstance(exc, dict):
            _error(f"css.exceptions[{idx}] must be a dict with non-blank file/property/value/reason", errors)
            continue
        for field in ("file", "property", "value", "reason"):
            if not _nonblank(exc.get(field)):
                _error(f"css.exceptions[{idx}].{field} must be non-blank", errors)
        if all(_nonblank(exc.get(f)) for f in ("file", "property", "value", "reason")):
            declared_exceptions.append({
                "file": exc["file"].strip(),
                "property": exc["property"].strip(),
                "value": exc["value"].strip(),
            })

    # Regex patterns
    decl_re = re.compile(r"([\w-]+)\s*:\s*([^;}]*)[;}]")
    monitored_properties = {
        "color",
        "background-color",
        "border-color",
        "outline-color",
        "fill",
        "stroke",
        "box-shadow",
        "text-shadow",
        "border-radius",
        "font-family",
        "font-size",
    }

    # Allow reset keywords
    reset_keywords = {"inherit", "initial", "unset", "revert", "transparent", "none", "currentcolor"}

    # Track used exceptions
    used_exception_indices = set()

    for css_path_str in css_files:
        # Resolve path: absolute as-is, relative from repo root
        p = Path(css_path_str)
        if p.is_absolute():
            css_file = p
        else:
            css_file = repo_root / css_path_str

        if not css_file.is_file():
            _error(f"CSS file not found: {css_path_str} (resolved: {css_file})", errors)
            continue

        try:
            content = css_file.read_text(encoding="utf-8")
        except OSError as exc:
            _error(f"Cannot read CSS file {css_path_str}: {exc}", errors)
            continue

        # Strip comments and @font-face blocks before scanning declarations
        content = _strip_css_comments(content)
        content = _strip_css_blocks(content)

        for match in decl_re.finditer(content):
            prop = match.group(1).strip().lower()
            raw_value = match.group(2).strip()
            norm_value = re.sub(r"\s+", " ", raw_value)

            if prop not in monitored_properties:
                continue

            # Check for var() references: validate all var names, then strip
            # full balanced var(...) functions from a copy. If nothing
            # meaningful remains (only whitespace/!important), the value is
            # fully token-driven and passes. Otherwise the exact-exception or
            # raw-one-off flow runs, so mixed box-shadow fails while
            # var(--token, fallback) passes.
            var_matches = _extract_var_refs(raw_value)
            if var_matches:
                undefined = False
                for var_name in var_matches:
                    if var_name not in declared_var_names:
                        _error(
                            f"Undefined CSS variable '{var_name}' in {css_path_str} "
                            f"({prop}: {raw_value})",
                            errors,
                        )
                        undefined = True
                if undefined:
                    continue
                remainder = _meaningful_remainder(raw_value)
                if remainder == "" or remainder.lower() in reset_keywords:
                    continue
                # Fall through to exception / raw-one-off check
                norm_value = re.sub(r"\s+", " ", remainder).strip()
            else:
                norm_value = re.sub(r"\s+", " ", raw_value).strip()

            if norm_value.lower() in reset_keywords:
                continue

            # Check against exceptions
            exc_matched = False
            for exc_idx, exc in enumerate(declared_exceptions):
                exc_file = exc["file"]
                exc_prop = exc["property"].lower()
                exc_value = re.sub(r"\s+", " ", exc["value"])
                file_match = (
                    css_path_str == exc_file
                    or str(css_file) == exc_file
                    or css_file.name == exc_file
                )
                prop_match = prop == exc_prop
                val_match = norm_value == exc_value
                if file_match and prop_match and val_match:
                    exc_matched = True
                    used_exception_indices.add(exc_idx)
                    break

            if exc_matched:
                continue

            _error(
                f"Non-token value in {css_path_str} ({prop}: {raw_value}). "
                "Use a design-token variable or add an exception with a reason.",
                errors,
            )

    # Reject unused exceptions
    for exc_idx in range(len(declared_exceptions)):
        if exc_idx not in used_exception_indices:
            exc = declared_exceptions[exc_idx]
            _error(
                f"Unused CSS exception for file '{exc['file']}', property '{exc['property']}', value '{exc['value']}'",
                errors,
            )

    # --require-exports
    loaded_exports = {}
    if args.require_exports:
        exports = data.get("exports", {})
        if not isinstance(exports, dict):
            _error("exports must be a dict; missing required keys variables, presets, themeBuilder", errors)
        else:
            required_export_keys = {"variables", "presets", "themeBuilder"}
            for key in required_export_keys:
                path_str = exports.get(key)
                if not path_str:
                    _error(f"exports.{key} is missing or empty", errors)
                    continue
                export_path = _resolve_path(path_str, manifest_path.parent)
                if not export_path.is_file():
                    _error(f"exports.{key} file not found: {export_path}", errors)
                    continue
                try:
                    with export_path.open("r", encoding="utf-8") as f:
                        export_data = json.load(f)
                except (json.JSONDecodeError, OSError) as exc:
                    _error(f"exports.{key} invalid JSON: {exc}", errors)
                    continue
                if not isinstance(export_data, dict):
                    _error(f"exports.{key} must be a JSON object", errors)
                    continue
                if len(export_data) == 0:
                    _error(f"exports.{key} is an empty object", errors)
                    continue
                if _has_placeholder(export_data):
                    _error(f"exports.{key} contains placeholder/synthetic/fabricated marker", errors)
                loaded_exports[key] = export_data

            # Cross-check titles in exports using loaded data
            var_data = loaded_exports.get("variables")
            if var_data is not None:
                var_strings = set(_collect_strings(var_data))
                for title in all_token_titles:
                    if title not in var_strings:
                        _error(f"Token title '{title}' missing from variables export", errors)

            pres_data = loaded_exports.get("presets")
            if pres_data is not None:
                pres_strings = set(_collect_strings(pres_data))
                for title in all_preset_titles:
                    if title not in pres_strings:
                        _error(f"Preset title '{title}' missing from presets export", errors)

            tb_data = loaded_exports.get("themeBuilder")
            if tb_data is not None:
                tb_strings = set(_collect_strings(tb_data))
                for title in all_template_titles:
                    if title not in tb_strings:
                        _error(f"Template title '{title}' missing from themeBuilder export", errors)

    if errors:
        print("FAIL")
        for err in errors:
            print(f"  - {err}")
        return 1

    print("PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
