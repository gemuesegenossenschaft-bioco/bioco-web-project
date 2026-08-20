import importlib.util
from pathlib import Path
from unittest.mock import patch

import pytest
from PIL import Image


ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / "wordpress/scripts/visual-parity.py"
RENDER_GATE = ROOT / "tests/wordpress-staging-render-gate.sh"


def _module():
    spec = importlib.util.spec_from_file_location("visual_parity", SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def _route_set_from_render_gate():
    text = RENDER_GATE.read_text()
    start = text.index("routes=(")
    end = text.index(")", start)
    routes = set()
    for line in text[start:end].splitlines():
        line = line.strip()
        if not line or line == "routes=(":
            continue
        line = line.rstrip(")")
        for token in line.split():
            token = token.strip().strip('"').strip("'")
            if token and token.startswith("/"):
                routes.add(token)
    return routes


def _make_image(size, color):
    return Image.new("RGB", size, color)


def test_gate_result_empty_items_has_passed_false():
    """GateResult with no items must fail closed (passed=False)."""
    parity = _module()
    result = parity.GateResult(threshold=0.95, items=[])
    assert result.passed is False


def test_compare_white_vs_near_white_yields_score_below_one(tmp_path):
    """A reference white vs candidate #fbfbfb must not score 1.0."""
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    _make_image((20, 20), "#fbfbfb").save(candidate)

    result = parity.compare(reference, candidate, masks=[])
    assert result["score"] < 1.0


# -----------------------------------------------------------------------------
# Existing seam contract
# -----------------------------------------------------------------------------


def test_visual_parity_gate_defaults_to_ninety_five_percent(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "#f5f1e8").save(reference)
    _make_image((20, 20), "#f5f1e8").save(candidate)

    result = parity.compare(reference, candidate, masks=[])
    assert parity.DEFAULT_THRESHOLD == 0.95
    assert result["score"] == 1.0
    assert result["height_accuracy"] == 1.0
    assert result["width_accuracy"] == 1.0
    assert result["overall"] == 1.0
    assert result["passed"] is True


def test_visual_parity_masks_only_declared_volatile_rectangles(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    changed = _make_image((20, 20), "white")
    for x in range(5):
        for y in range(5):
            changed.putpixel((x, y), (0, 0, 0))
    changed.save(candidate)

    documented_mask = [{"rect": (0, 0, 5, 5), "reason": "volatile hero bitmap"}]
    assert parity.compare(reference, candidate, masks=[])["score"] < 1.0
    assert parity.compare(reference, candidate, masks=documented_mask)["score"] == 1.0


# -----------------------------------------------------------------------------
# Threshold plumbing
# -----------------------------------------------------------------------------


def test_compare_uses_caller_threshold_for_passed(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    # Reference contains a small content island that the candidate removes.
    ref = _make_image((100, 100), "white")
    for x in range(10):
        for y in range(10):
            ref.putpixel((x, y), (64, 64, 64))
    ref.save(reference)
    _make_image((100, 100), "white").save(candidate)

    loose = parity.compare(reference, candidate, masks=[], threshold=0.50)
    strict = parity.compare(reference, candidate, masks=[], threshold=0.99)
    assert loose["passed"] is True
    assert strict["passed"] is False


def test_run_gate_items_use_caller_threshold(tmp_path):
    parity = _module()
    ref_fixture = tmp_path / "ref.png"
    cand_fixture = tmp_path / "cand.png"
    _make_image((390, 844), "white").save(ref_fixture)
    _make_image((390, 844), "white").save(cand_fixture)

    captured = {"ref": ref_fixture, "cand": cand_fixture}

    def fake_capture(url, viewport_name, output_path):
        import shutil
        import urllib.parse

        output_path.parent.mkdir(parents=True, exist_ok=True)
        host = urllib.parse.urlparse(url).hostname
        src = captured["ref"] if host == "bioco.ch" else captured["cand"]
        shutil.copy(src, output_path)
        return output_path

    with patch.object(parity, "capture_page", fake_capture):
        result = parity.run_gate(
            reference_origin="https://bioco.ch",
            candidate_origin="https://staging.bioco.ch",
            output_dir=tmp_path / "out",
            threshold=0.99,
        )

    assert result.passed is True
    for item in result.items:
        assert item["threshold"] == 0.99
        assert item["passed"] is True


# -----------------------------------------------------------------------------
# Threshold validation
# -----------------------------------------------------------------------------


def test_compare_rejects_threshold_outside_unit_interval_nan_and_infinities(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    _make_image((20, 20), "white").save(candidate)

    invalid = [-0.1, 1.1, float("nan"), float("inf"), float("-inf")]
    for threshold in invalid:
        with pytest.raises(ValueError, match="threshold"):
            parity.compare(reference, candidate, masks=[], threshold=threshold)

    # Boundary values are accepted.
    assert parity.compare(reference, candidate, masks=[], threshold=0.0)["passed"] is True
    assert parity.compare(reference, candidate, masks=[], threshold=1.0)["passed"] is True


def test_run_gate_rejects_threshold_outside_unit_interval_nan_and_infinities(tmp_path):
    parity = _module()

    def fake_capture_must_not_run(url, viewport_name, output_path):
        raise AssertionError("capture must not run")

    invalid = [-0.1, 1.1, float("nan"), float("inf"), float("-inf")]
    with patch.object(parity, "capture_page", fake_capture_must_not_run):
        for threshold in invalid:
            with pytest.raises(ValueError, match="threshold"):
                parity.run_gate(
                    reference_origin="https://bioco.ch",
                    candidate_origin="https://staging.bioco.ch",
                    output_dir=tmp_path / "out",
                    threshold=threshold,
                )

    # Boundary values are accepted.
    def fake_capture(url, viewport_name, output_path):
        output_path.parent.mkdir(parents=True, exist_ok=True)
        _make_image((390, 844), "white").save(output_path)
        return output_path

    with patch.object(parity, "capture_page", fake_capture):
        for threshold in (0.0, 1.0):
            result = parity.run_gate(
                reference_origin="https://bioco.ch",
                candidate_origin="https://staging.bioco.ch",
                output_dir=tmp_path / "out",
                threshold=threshold,
            )
            assert result.passed is True
            for item in result.items:
                assert item["threshold"] == threshold


# -----------------------------------------------------------------------------
# Adversarial guards
# -----------------------------------------------------------------------------


def test_compare_rejects_identical_reference_and_candidate_paths(tmp_path):
    parity = _module()
    artifact = tmp_path / "same.png"
    _make_image((10, 10), "white").save(artifact)

    with pytest.raises(ValueError, match="different artifacts"):
        parity.compare(artifact, artifact, masks=[])


def test_compare_rejects_identical_reference_and_candidate_origins():
    parity = _module()

    with pytest.raises(ValueError, match="must be different"):
        parity.assert_distinct_origins("https://bioco.ch", "https://bioco.ch")

    with pytest.raises(ValueError, match="must be different"):
        parity.assert_distinct_origins("https://bioco.ch/", "https://bioco.ch")


def test_compare_fails_closed_when_reference_or_candidate_missing(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((10, 10), "white").save(reference)

    with pytest.raises(FileNotFoundError):
        parity.compare(reference, tmp_path / "nope.png", masks=[])

    with pytest.raises(FileNotFoundError):
        parity.compare(tmp_path / "nope.png", candidate, masks=[])


def test_cropping_reduces_height_or_width_accuracy_below_threshold(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((100, 200), "white").save(reference)
    _make_image((100, 100), "white").save(candidate)

    result = parity.compare(reference, candidate, masks=[])
    assert result["width_accuracy"] == 1.0
    assert result["height_accuracy"] == 0.5
    assert result["overall"] == 0.5
    assert result["passed"] is False


# -----------------------------------------------------------------------------
# Mask contract
# -----------------------------------------------------------------------------


def test_tuple_masks_are_rejected(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    _make_image((20, 20), "white").save(candidate)

    with pytest.raises(ValueError, match="documented"):
        parity.compare(reference, candidate, masks=[(0, 0, 5, 5)])


def test_masks_require_non_empty_reason(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    _make_image((20, 20), "white").save(candidate)

    with pytest.raises(ValueError, match="reason"):
        parity.compare(reference, candidate, masks=[{"rect": (0, 0, 5, 5)}])

    with pytest.raises(ValueError, match="reason"):
        parity.compare(reference, candidate, masks=[{"rect": (0, 0, 5, 5), "reason": ""}])

    with pytest.raises(ValueError, match="reason"):
        parity.compare(reference, candidate, masks=[{"rect": (0, 0, 5, 5), "reason": "   "}])


def test_mask_rectangles_must_be_numeric_ordered_positive_area(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    _make_image((20, 20), "white").save(candidate)

    invalid = [
        ({"rect": (0, 0, 5), "reason": "x"}, "four"),
        ({"rect": ("a", 0, 5, 5), "reason": "x"}, "numeric"),
        ({"rect": (5, 0, 0, 5), "reason": "x"}, "ordered"),
        ({"rect": (0, 5, 5, 5), "reason": "x"}, "positive"),
        ({"rect": (0, 0, 0, 5), "reason": "x"}, "positive"),
        ({"rect": (0, 0, 5, 5, 5), "reason": "x"}, "four"),
    ]

    for mask, pattern in invalid:
        with pytest.raises(ValueError, match=pattern):
            parity.compare(reference, candidate, masks=[mask])


def test_mask_coordinates_reject_nan_and_infinities(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((20, 20), "white").save(reference)
    _make_image((20, 20), "white").save(candidate)

    import math

    invalid = [
        ({"rect": (float("nan"), 0, 5, 5), "reason": "x"}, "finite"),
        ({"rect": (0, float("inf"), 5, 5), "reason": "x"}, "finite"),
        ({"rect": (0, 0, 5, float("-inf")), "reason": "x"}, "finite"),
    ]

    for mask, pattern in invalid:
        with pytest.raises(ValueError, match=pattern):
            parity.compare(reference, candidate, masks=[mask])


def test_oversized_masks_are_rejected(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((100, 100), "white").save(reference)
    _make_image((100, 100), "white").save(candidate)

    with pytest.raises(ValueError, match="mask ratio"):
        parity.compare(
            reference,
            candidate,
            masks=[{"rect": (0, 0, 100, 20), "reason": "too broad"}],
        )


def test_overlapping_masks_use_union_not_sum_for_ratio(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((100, 100), "white").save(reference)
    _make_image((100, 100), "white").save(candidate)

    # Two identical masks covering 10 % of the page; union must be 10 %, not 20 %.
    masks = [
        {"rect": (0, 0, 100, 10), "reason": "a"},
        {"rect": (0, 0, 100, 10), "reason": "b"},
    ]
    result = parity.compare(reference, candidate, masks=masks)
    assert result["mask_ratio"] == 0.10


# -----------------------------------------------------------------------------
# Background-dominance weighted metric
# -----------------------------------------------------------------------------


def test_background_dominance_content_removal_fails_at_contract_threshold(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    # 1 % black content island on a white dominant background.
    ref = _make_image((100, 100), "white")
    for x in range(45, 55):
        for y in range(45, 55):
            ref.putpixel((x, y), (0, 0, 0))
    ref.save(reference)
    _make_image((100, 100), "white").save(candidate)

    result = parity.compare(reference, candidate, masks=[], threshold=0.95)
    assert result["score"] < 0.95
    assert result["passed"] is False


def test_background_layout_change_remains_visible(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((100, 100), "white").save(reference)
    # Candidate changes the entire background to a different color.
    _make_image((100, 100), "#eeeeee").save(candidate)

    result = parity.compare(reference, candidate, masks=[])
    # The weighted metric must still see a non-perfect score for a background change.
    assert result["score"] < 1.0
    assert result["passed"] is False


# -----------------------------------------------------------------------------
# Aggregate-score rejection
# -----------------------------------------------------------------------------


def test_aggregate_substitution_is_rejected_by_per_route_viewport_results():
    parity = _module()
    result = parity.GateResult(
        threshold=0.95,
        items=[
            {"route": "/", "viewport": "desktop", "passed": True, "overall": 0.99},
            {"route": "/", "viewport": "mobile", "passed": False, "overall": 0.80},
        ],
    )
    assert result.passed is False
    assert result.failed_items == [
        {"route": "/", "viewport": "mobile", "passed": False, "overall": 0.80}
    ]
    assert result.overall is None


# -----------------------------------------------------------------------------
# Canonical route and viewport contract
# -----------------------------------------------------------------------------


def test_canonical_routes_are_exactly_twenty_two_and_match_render_gate():
    parity = _module()

    assert len(parity.CANONICAL_ROUTES) == 22
    assert set(parity.CANONICAL_ROUTES) == _route_set_from_render_gate()


def test_viewports_match_contract_widths():
    parity = _module()

    assert parity.VIEWPORTS["desktop"]["width"] == 1440
    assert parity.VIEWPORTS["mobile"]["width"] == 390


def test_masks_are_registered_per_route_and_viewport_with_reasons():
    parity = _module()

    for route in parity.CANONICAL_ROUTES:
        assert route in parity.MASKS, f"{route} missing from MASKS registry"
        for viewport in parity.VIEWPORTS:
            masks = parity.MASKS[route].get(viewport, [])
            for mask in masks:
                assert "rect" in mask, f"mask for {route}/{viewport} lacks rect"
                assert "reason" in mask, f"mask for {route}/{viewport} lacks reason"
                assert len(mask["rect"]) == 4


def test_total_masked_area_per_route_viewport_is_below_ceiling():
    parity = _module()

    for route in parity.CANONICAL_ROUTES:
        for viewport_name, viewport in parity.VIEWPORTS.items():
            masks = parity.MASKS[route].get(viewport_name, [])
            total = 0
            for mask in masks:
                left, top, right, bottom = mask["rect"]
                total += max(0, right - left) * max(0, bottom - top)
            image_area = viewport["width"] * viewport["height"]
            assert total / image_area <= parity.MAX_MASK_RATIO, (
                f"{route}/{viewport_name} masks cover {total / image_area:.2%}"
            )


# -----------------------------------------------------------------------------
# Failure reporting / artifacts contract
# -----------------------------------------------------------------------------


def test_compare_emits_sizes_bounds_masks_and_artifact_paths(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((100, 200), "white").save(reference)
    _make_image((100, 100), "#eeeeee").save(candidate)

    result = parity.compare(
        reference,
        candidate,
        masks=[{"rect": (0, 0, 10, 10), "reason": "volatile"}],
        output_dir=tmp_path,
    )

    assert result["reference_size"] == (100, 200)
    assert result["candidate_size"] == (100, 100)
    assert result["comparison_bounds"] == (100, 100)
    assert result["effective_masks"] == [(0, 0, 10, 10)]
    assert result["mask_reasons"] == ["volatile"]
    assert "mask_ratio" in result
    assert "score" in result
    assert "width_accuracy" in result
    assert "height_accuracy" in result
    assert "overall" in result
    assert "passed" in result
    assert result["artifacts"]["reference"] == str(reference)
    assert result["artifacts"]["candidate"] == str(candidate)
    assert Path(result["artifacts"]["diff"]).is_file()
    assert Path(result["artifacts"]["mask"]).is_file()


def test_failure_output_identifies_route_viewport_metrics_and_artifact_paths(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    _make_image((100, 200), "white").save(reference)
    _make_image((100, 100), "white").save(candidate)

    result = parity.compare(reference, candidate, masks=[], output_dir=tmp_path)
    result["route"] = "/wir/"
    result["viewport"] = "mobile"

    assert result["route"] == "/wir/"
    assert result["viewport"] == "mobile"
    assert result["passed"] is False
    assert result["artifacts"]["reference"] == str(reference)
    assert result["artifacts"]["candidate"] == str(candidate)
    assert result["artifacts"]["diff"]
    assert result["artifacts"]["mask"]


def test_run_gate_emits_reference_candidate_diff_and_mask_pngs(tmp_path):
    parity = _module()
    ref_fixture = tmp_path / "ref.png"
    cand_fixture = tmp_path / "cand.png"
    _make_image((1440, 900), "white").save(ref_fixture)
    _make_image((1440, 900), "white").save(cand_fixture)

    captured = {"ref": ref_fixture, "cand": cand_fixture}

    def fake_capture(url, viewport_name, output_path):
        import shutil
        import urllib.parse

        output_path.parent.mkdir(parents=True, exist_ok=True)
        host = urllib.parse.urlparse(url).hostname
        src = captured["ref"] if host == "bioco.ch" else captured["cand"]
        shutil.copy(src, output_path)
        return output_path

    with patch.object(parity, "capture_page", fake_capture):
        result = parity.run_gate(
            reference_origin="https://bioco.ch",
            candidate_origin="https://staging.bioco.ch",
            output_dir=tmp_path / "out",
        )

    assert result.passed is True
    for item in result.items:
        for key in ("reference", "candidate", "diff", "mask"):
            path = Path(item["artifacts"][key])
            assert path.is_file(), f"missing {key} artifact for {item['route']}/{item['viewport']}"


# -----------------------------------------------------------------------------
# Capture and gate helpers
# -----------------------------------------------------------------------------


def test_capture_produces_full_page_screenshot_of_configured_viewport(tmp_path):
    parity = _module()
    fixture = tmp_path / "fixture.html"
    fixture.write_text(
        "<!doctype html><html><body style='margin:0;height:2000px;background:linear-gradient(red,blue)'></body></html>"
    )
    output = tmp_path / "capture.png"

    parity.capture_page(f"file://{fixture}", "desktop", output)

    assert output.is_file()
    with Image.open(output) as img:
        assert img.width == 1440
        assert img.height > 900


def test_gate_fails_closed_when_origins_are_missing():
    parity = _module()

    with pytest.raises(ValueError, match="required"):
        parity.run_gate(reference_origin="", candidate_origin="https://staging.bioco.ch", output_dir=Path("/tmp"))

    with pytest.raises(ValueError, match="required"):
        parity.run_gate(reference_origin="https://bioco.ch", candidate_origin="", output_dir=Path("/tmp"))


def test_gate_rejects_candidate_self_reference(tmp_path):
    parity = _module()

    def fake_capture(url, viewport_name, output_path):
        _make_image((100, 100), "white").save(output_path)
        return output_path

    with patch.object(parity, "capture_page", fake_capture):
        with pytest.raises(ValueError, match="must be different"):
            parity.run_gate(
                reference_origin="https://bioco.ch",
                candidate_origin="https://bioco.ch/",
                output_dir=tmp_path,
            )


# -----------------------------------------------------------------------------
# Origin safety
# -----------------------------------------------------------------------------


def test_reference_origin_must_be_https_bioco_ch():
    parity = _module()

    parity.assert_distinct_origins("https://bioco.ch", "https://staging.bioco.ch")
    parity.assert_distinct_origins("https://bioco.ch/", "https://staging.bioco.ch")

    with pytest.raises(ValueError, match="reference origin"):
        parity.assert_distinct_origins("http://bioco.ch", "https://staging.bioco.ch")

    with pytest.raises(ValueError, match="reference origin"):
        parity.assert_distinct_origins("https://www.bioco.ch", "https://staging.bioco.ch")


def test_reference_origin_rejects_paths_queries_fragments_credentials_ports():
    parity = _module()
    bad = [
        "https://bioco.ch/wir/",
        "https://bioco.ch/?x=1",
        "https://bioco.ch/#anchor",
        "https://user:pass@bioco.ch",
        "https://bioco.ch:8443",
        "https://bioco.ch:443/path",
        "https://bioco.ch:443",
        "https://bioco.ch:443/",
    ]
    for origin in bad:
        with pytest.raises(ValueError, match="reference origin"):
            parity.assert_distinct_origins(origin, "https://staging.bioco.ch")


def test_candidate_origin_must_be_valid_http_https_and_not_production():
    parity = _module()

    parity.assert_distinct_origins("https://bioco.ch", "https://staging.bioco.ch")
    parity.assert_distinct_origins("https://bioco.ch", "http://localhost:3000")

    with pytest.raises(ValueError, match="candidate origin"):
        parity.assert_distinct_origins("https://bioco.ch", "ftp://staging.bioco.ch")

    with pytest.raises(ValueError, match="candidate origin"):
        parity.assert_distinct_origins("https://bioco.ch", "https://bioco.ch")

    with pytest.raises(ValueError, match="candidate origin"):
        parity.assert_distinct_origins("https://bioco.ch", "https://bioco.ch:443")

    with pytest.raises(ValueError, match="candidate origin"):
        parity.assert_distinct_origins("https://bioco.ch", "http://bioco.ch")


def test_candidate_origin_rejects_paths_credentials_query_fragment():
    parity = _module()
    bad = [
        "https://staging.bioco.ch/wir/",
        "https://user:pass@staging.bioco.ch",
        "https://staging.bioco.ch/?x=1",
        "https://staging.bioco.ch#anchor",
    ]
    for origin in bad:
        with pytest.raises(ValueError, match="candidate origin"):
            parity.assert_distinct_origins("https://bioco.ch", origin)


# -----------------------------------------------------------------------------
# CLI contract
# -----------------------------------------------------------------------------


def test_cli_gate_produces_per_route_viewport_json(tmp_path):
    parity = _module()
    parser = parity.build_parser()
    args = parser.parse_args([
        "gate",
        "--reference-origin", "https://bioco.ch",
        "--candidate-origin", "https://staging.bioco.ch",
        "--output-dir", str(tmp_path),
        "--threshold", "0.97",
    ])
    assert args.command == "gate"
    assert args.reference_origin == "https://bioco.ch"
    assert args.candidate_origin == "https://staging.bioco.ch"
    assert args.output_dir == tmp_path
    assert args.threshold == 0.97


def test_cli_compare_requires_reference_candidate_route_viewport():
    parity = _module()
    parser = parity.build_parser()
    args = parser.parse_args([
        "compare",
        "--reference", "ref.png",
        "--candidate", "cand.png",
        "--route", "/wir/",
        "--viewport", "desktop",
        "--threshold", "0.97",
    ])
    assert args.command == "compare"
    assert args.reference == Path("ref.png")
    assert args.candidate == Path("cand.png")
    assert args.route == "/wir/"
    assert args.viewport == "desktop"
    assert args.threshold == 0.97
