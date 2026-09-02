#!/usr/bin/env python3
"""Measured rendered-browser parity gate for bioco.ch WordPress routes.

Reference origin:  current production https://bioco.ch
Candidate origin:  configurable WordPress staging/local origin, normally
                   https://staging.bioco.ch

This script captures full-page rendered-browser screenshots of every canonical
seed route at 1440px and 390px viewports and compares them against the
production reference.  It fails closed if origins/artifacts are missing, if the
reference and candidate origins are identical, or if comparison inputs are
invalid.

Pixel similarity is a single content-aware weighted score.  Pixels that differ
from the reference's dominant border background carry more weight than
background pixels, so removing a small content island cannot be hidden by a
large matching background.
"""

import argparse
import json
import math
import sys
import urllib.parse
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw


DEFAULT_THRESHOLD = 0.95
MAX_MASK_RATIO = 0.15
BG_TOLERANCE = 8  # pixels within this distance from background are down-weighted
WEIGHT_BASELINE = 16  # minimum nonzero weight so background/layout changes count
WEIGHT_MAX = 255

PRODUCTION_ORIGIN = "https://bioco.ch"

CANONICAL_ROUTES = [
    "/",
    "/abos/",
    "/aktuelles/",
    "/anmeldung/",
    "/anmeldung-danke/",
    "/bioco-werden/",
    "/datenschutz/",
    "/gemuese/",
    "/impressum/",
    "/kontakt/",
    "/kundenportal/",
    "/mitmachen/",
    "/newsletter/",
    "/newsletter-bestaetigen/",
    "/event-anmeldung/",
    "/solawi/",
    "/standorte-depots/",
    "/statuten/",
    "/tag-der-offenen-tuer/",
    "/warteliste/",
    "/wir/",
    "/intranet/",
]

VIEWPORTS = {
    "desktop": {"width": 1440, "height": 900, "device_scale_factor": 1},
    "mobile": {"width": 390, "height": 844, "device_scale_factor": 1},
}

# Per-route, per-viewport masks for specifically documented nondeterministic
# DOM surfaces.  Each entry is {"rect": (left, top, right, bottom), "reason": "..."}.
# Rectangles are clamped to the captured screenshot bounds at comparison time.
MASKS = {route: {"desktop": [], "mobile": []} for route in CANONICAL_ROUTES}


class GateResult:
    """Per-route/viewport gate result.  Never collapses to a single score."""

    def __init__(self, threshold, items):
        self.threshold = threshold
        self.items = items

    @property
    def passed(self):
        return bool(self.items) and all(item.get("passed", False) for item in self.items)

    @property
    def failed_items(self):
        return [item for item in self.items if not item.get("passed", False)]

    @property
    def overall(self):
        # Explicitly never produce an aggregate score.
        return None

    def to_dict(self):
        return {
            "threshold": self.threshold,
            "passed": self.passed,
            "items": self.items,
            "failed_items": self.failed_items,
        }


def _parse_origin(origin):
    parsed = urllib.parse.urlparse(origin.strip())
    if not parsed.scheme or not parsed.netloc:
        raise ValueError(f"invalid origin: {origin}")
    return parsed


def _normalize_origin(origin):
    """Return origin stripped of trailing slash and lower-cased for comparison."""
    parsed = urllib.parse.urlparse(origin.strip().lower())
    netloc = parsed.netloc or parsed.path
    scheme = parsed.scheme or "https"
    return f"{scheme}://{netloc.rstrip('/')}".rstrip('/')


def assert_reference_origin(origin):
    parsed = _parse_origin(origin)
    if parsed.scheme != "https":
        raise ValueError("reference origin must use https")
    host = parsed.hostname
    if not host or host.lower() != "bioco.ch":
        raise ValueError("reference origin must be https://bioco.ch")
    if parsed.port:
        raise ValueError("reference origin must not specify a port")
    if parsed.path and parsed.path != "/":
        raise ValueError("reference origin must not contain a path")
    if parsed.params or parsed.query or parsed.fragment:
        raise ValueError("reference origin must not contain params/query/fragment")
    if parsed.username or parsed.password:
        raise ValueError("reference origin must not contain credentials")


def assert_candidate_origin(origin):
    parsed = _parse_origin(origin)
    if parsed.scheme not in ("http", "https"):
        raise ValueError("candidate origin must use http or https")
    if not parsed.hostname:
        raise ValueError("candidate origin must have a host")
    if parsed.path and parsed.path != "/":
        raise ValueError("candidate origin must not contain a path")
    if parsed.params or parsed.query or parsed.fragment:
        raise ValueError("candidate origin must not contain params/query/fragment")
    if parsed.username or parsed.password:
        raise ValueError("candidate origin must not contain credentials")
    if parsed.hostname.lower() == "bioco.ch":
        raise ValueError("candidate origin must not point to production")


def assert_distinct_origins(reference_origin, candidate_origin):
    if not reference_origin or not candidate_origin:
        raise ValueError("reference and candidate origins are required")
    if _normalize_origin(reference_origin) == _normalize_origin(candidate_origin):
        raise ValueError("reference and candidate origins must be different")
    assert_reference_origin(reference_origin)
    assert_candidate_origin(candidate_origin)


def _assert_valid_threshold(threshold):
    if not isinstance(threshold, (int, float)) or not math.isfinite(threshold) or threshold < 0.0 or threshold > 1.0:
        raise ValueError("threshold must be a finite number between 0.0 and 1.0")


def _dominant_border_background(image):
    """Return the most frequent color on the image border."""
    width, height = image.size
    if width < 2 or height < 2:
        return image.getpixel((0, 0))

    top = image.crop((0, 0, width, 1))
    bottom = image.crop((0, height - 1, width, height))
    left = image.crop((0, 0, 1, height))
    right = image.crop((width - 1, 0, width, height))

    counts = {}
    for border in (top, bottom, left, right):
        for color in border.getdata():
            counts[color] = counts.get(color, 0) + 1
    return max(counts, key=counts.get)


def _content_weight_image(reference):
    """Return an L-mode weight image where background pixels are down-weighted."""
    bg = _dominant_border_background(reference)
    bg_image = Image.new("RGB", reference.size, bg)
    distance = ImageChops.difference(reference, bg_image).convert("L")
    # Map distance 0..255 to weight WEIGHT_BASELINE..WEIGHT_MAX.
    # Pixels close to background receive WEIGHT_BASELINE; content pixels receive up to WEIGHT_MAX.
    return distance.point(
        lambda v: WEIGHT_BASELINE
        + int((WEIGHT_MAX - WEIGHT_BASELINE) * min(v, 255) / 255)
    )


def _validate_mask(mask):
    if not isinstance(mask, dict):
        raise ValueError("masks must be documented dicts with 'rect' and 'reason'")
    if "rect" not in mask or "reason" not in mask:
        raise ValueError("mask must have 'rect' and 'reason'")
    reason = mask["reason"]
    if not isinstance(reason, str) or not reason.strip():
        raise ValueError("mask reason must be a non-empty string")
    rect = mask["rect"]
    if not isinstance(rect, (list, tuple)) or len(rect) != 4:
        raise ValueError("mask rect must have four numeric values")
    try:
        left, top, right, bottom = (float(v) for v in rect)
    except (TypeError, ValueError):
        raise ValueError("mask rect must have four numeric values")
    if not all(math.isfinite(v) for v in (left, top, right, bottom)):
        raise ValueError("mask rect must have finite numeric values")
    if left > right or top > bottom:
        raise ValueError("mask rect must be ordered with left<right and top<bottom")
    if left == right or top == bottom:
        raise ValueError("mask rect must have positive area")
    return left, top, right, bottom, reason


def _normalize_masks(masks, image_size):
    """Return list of ((left, top, right, bottom), reason) clamped to image."""
    width, height = image_size
    normalized = []
    for mask in masks:
        left, top, right, bottom, reason = _validate_mask(mask)
        clamped = (
            max(0, min(int(left), width)),
            max(0, min(int(top), height)),
            max(0, min(int(right), width)),
            max(0, min(int(bottom), height)),
        )
        if clamped[0] < clamped[2] and clamped[1] < clamped[3]:
            normalized.append((clamped, reason))
    return normalized


def _union_mask_area(normalized_masks, image_size):
    """Return the pixel area covered by the union of mask rectangles."""
    if not normalized_masks:
        return 0
    mask_image = Image.new("L", image_size, 0)
    draw = ImageDraw.Draw(mask_image)
    for (left, top, right, bottom), _ in normalized_masks:
        draw.rectangle((left, top, right - 1, bottom - 1), fill=255)
    return sum(mask_image.getdata()) // 255


def compare(reference_path, candidate_path, masks, threshold=DEFAULT_THRESHOLD, output_dir=None):
    """Compare two screenshot artifacts.

    Returns a dict with per-metric results plus effective mask rectangles,
    mask ratio, and artifact paths.  Raises on invalid inputs or disallowed
    masks.
    """
    _assert_valid_threshold(threshold)
    reference_path = Path(reference_path)
    candidate_path = Path(candidate_path)

    if reference_path == candidate_path:
        raise ValueError("reference and candidate must be different artifacts")
    if not reference_path.exists():
        raise FileNotFoundError(f"reference missing: {reference_path}")
    if not candidate_path.exists():
        raise FileNotFoundError(f"candidate missing: {candidate_path}")

    reference = Image.open(reference_path).convert("RGB")
    candidate = Image.open(candidate_path).convert("RGB")

    ref_size = reference.size
    cand_size = candidate.size
    width = min(ref_size[0], cand_size[0])
    height = min(ref_size[1], cand_size[1])
    comparison_bounds = (width, height)
    reference_crop = reference.crop((0, 0, width, height))
    candidate_crop = candidate.crop((0, 0, width, height))

    normalized_masks = _normalize_masks(masks, comparison_bounds)
    effective_rectangles = [rect for rect, _ in normalized_masks]
    mask_reasons = [reason for _, reason in normalized_masks]

    image_area = width * height
    mask_area = _union_mask_area(normalized_masks, comparison_bounds)
    mask_ratio = mask_area / image_area if image_area else 0.0
    if mask_ratio > MAX_MASK_RATIO:
        raise ValueError(
            f"mask ratio {mask_ratio:.4f} exceeds maximum {MAX_MASK_RATIO}; "
            "broad masks are not permitted"
        )

    # User masks define the active comparison region.
    active = Image.new("L", comparison_bounds, 255)
    draw = ImageDraw.Draw(active)
    for (left, top, right, bottom), _ in normalized_masks:
        draw.rectangle((left, top, right - 1, bottom - 1), fill=0)

    # Weighted pixel similarity: content pixels carry more weight than background.
    weight = _content_weight_image(reference_crop)
    difference = ImageChops.difference(reference_crop, candidate_crop).convert("L")
    # Keep multiply-based diff artifact for output, but compute score from
    # unquantized float arrays so near-identical colors (white vs #fbfbfb)
    # produce a non-zero difference.
    weighted_diff = ImageChops.multiply(difference, weight)

    active_pixels = sum(active.getdata()) // 255
    if active_pixels == 0:
        score = 1.0
    else:
        weighted_sum = 0.0
        weight_sum = 0.0
        for d, w, a in zip(difference.getdata(), weight.getdata(), active.getdata()):
            if a >= 128:
                weighted_sum += float(d) * float(w)
                weight_sum += float(w)
        if weight_sum == 0:
            score = 1.0
        else:
            score = 1.0 - (weighted_sum / (weight_sum * 255.0))

    height_accuracy = (
        min(ref_size[1], cand_size[1]) / max(ref_size[1], cand_size[1])
        if ref_size[1] and cand_size[1]
        else 0.0
    )
    width_accuracy = (
        min(ref_size[0], cand_size[0]) / max(ref_size[0], cand_size[0])
        if ref_size[0] and cand_size[0]
        else 0.0
    )
    overall = min(score, height_accuracy, width_accuracy)

    artifacts = {
        "reference": str(reference_path),
        "candidate": str(candidate_path),
        "diff": None,
        "mask": None,
    }

    if output_dir is not None:
        output_dir = Path(output_dir)
        output_dir.mkdir(parents=True, exist_ok=True)
        diff_path = output_dir / "diff.png"
        mask_path = output_dir / "mask.png"
        weighted_diff.save(diff_path)
        active.save(mask_path)
        artifacts["diff"] = str(diff_path)
        artifacts["mask"] = str(mask_path)

    return {
        "score": round(score, 6),
        "height_accuracy": round(height_accuracy, 6),
        "width_accuracy": round(width_accuracy, 6),
        "overall": round(overall, 6),
        "passed": overall >= threshold,
        "threshold": threshold,
        "effective_masks": effective_rectangles,
        "mask_reasons": mask_reasons,
        "mask_ratio": round(mask_ratio, 6),
        "reference_size": ref_size,
        "candidate_size": cand_size,
        "comparison_bounds": comparison_bounds,
        "artifacts": artifacts,
    }


def capture_page(url, viewport_name, output_path):
    """Capture a full-page screenshot of ``url`` at the named viewport."""
    # Import playwright lazily so the comparison seam can be imported and unit
    # tested without a browser installation.
    from playwright.sync_api import sync_playwright

    viewport = VIEWPORTS[viewport_name]
    output_path = Path(output_path)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(viewport=viewport)
        try:
            # networkidle never settles on the production site (Matomo beacon),
            # so wait for load + settled fonts + a fixed quiet period instead.
            page.goto(url, wait_until="load", timeout=60_000)
            page.wait_for_function("document.fonts.status === 'loaded'", timeout=15_000)
            page.wait_for_timeout(1_500)
            page.screenshot(path=str(output_path), full_page=True)
        finally:
            browser.close()

    return output_path


def _artifact_dir(output_dir, route, viewport):
    safe_route = route.strip("/").replace("/", "_") or "home"
    return Path(output_dir) / f"{safe_route}-{viewport}"


def _artifact_path(artifact_dir, suffix):
    return Path(artifact_dir) / f"{suffix}.png"


def run_gate(reference_origin, candidate_origin, output_dir, threshold=DEFAULT_THRESHOLD):
    """Capture and compare every canonical route at every viewport."""
    _assert_valid_threshold(threshold)
    assert_distinct_origins(reference_origin, candidate_origin)

    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    items = []
    for route in CANONICAL_ROUTES:
        for viewport_name in VIEWPORTS:
            ref_url = f"{reference_origin.rstrip('/')}{route}"
            cand_url = f"{candidate_origin.rstrip('/')}{route}"
            artifact_dir = _artifact_dir(output_dir, route, viewport_name)
            ref_path = _artifact_path(artifact_dir, "reference")
            cand_path = _artifact_path(artifact_dir, "candidate")

            capture_page(ref_url, viewport_name, ref_path)
            capture_page(cand_url, viewport_name, cand_path)

            masks = MASKS[route].get(viewport_name, [])
            result = compare(ref_path, cand_path, masks, threshold=threshold, output_dir=artifact_dir)
            result["route"] = route
            result["viewport"] = viewport_name
            items.append(result)

    return GateResult(threshold=threshold, items=items)


def build_parser():
    parser = argparse.ArgumentParser(
        description="Rendered-browser parity gate for bioco.ch WordPress routes.",
    )
    subparsers = parser.add_subparsers(dest="command")

    compare_parser = subparsers.add_parser("compare", help="compare two screenshot artifacts")
    compare_parser.add_argument("--reference", type=Path, required=True)
    compare_parser.add_argument("--candidate", type=Path, required=True)
    compare_parser.add_argument("--route", default="/")
    compare_parser.add_argument("--viewport", default="desktop", choices=list(VIEWPORTS))
    compare_parser.add_argument("--threshold", type=float, default=DEFAULT_THRESHOLD)

    capture_parser = subparsers.add_parser("capture", help="capture a single screenshot")
    capture_parser.add_argument("--origin", required=True)
    capture_parser.add_argument("--route", default="/")
    capture_parser.add_argument("--viewport", default="desktop", choices=list(VIEWPORTS))
    capture_parser.add_argument("--output", type=Path, required=True)

    gate_parser = subparsers.add_parser("gate", help="run the full 22-route parity gate")
    gate_parser.add_argument("--reference-origin", required=True)
    gate_parser.add_argument("--candidate-origin", required=True)
    gate_parser.add_argument("--output-dir", type=Path, required=True)
    gate_parser.add_argument("--threshold", type=float, default=DEFAULT_THRESHOLD)

    return parser


def _legacy_compare(
    reference_desktop, candidate_desktop, reference_mobile, candidate_mobile, threshold
):
    """Backward-compatible homepage-only comparison used by older callers."""
    results = {
        "desktop": compare(
            reference_desktop,
            candidate_desktop,
            MASKS["/"]["desktop"],
            threshold=threshold,
        ),
        "mobile": compare(
            reference_mobile,
            candidate_mobile,
            MASKS["/"]["mobile"],
            threshold=threshold,
        ),
        "threshold": threshold,
    }
    results["passed"] = all(
        results[viewport]["passed"] for viewport in ("desktop", "mobile")
    )
    print(json.dumps(results, indent=2))
    return 0 if results["passed"] else 1


def main(argv=None):
    if argv is None:
        argv = sys.argv[1:]
    if argv and argv[0] in ("compare", "capture", "gate"):
        pass  # handled below
    elif argv and not argv[0].startswith("-"):
        # Unknown positional argument.
        parser = build_parser()
        parser.parse_args(argv)
        return 2
    else:
        # Legacy four-path invocation is still supported for older callers.
        legacy = argparse.ArgumentParser()
        legacy.add_argument("--reference-desktop", type=Path, required=True)
        legacy.add_argument("--candidate-desktop", type=Path, required=True)
        legacy.add_argument("--reference-mobile", type=Path, required=True)
        legacy.add_argument("--candidate-mobile", type=Path, required=True)
        legacy.add_argument("--threshold", type=float, default=DEFAULT_THRESHOLD)
        args = legacy.parse_args(argv)
        return _legacy_compare(
            args.reference_desktop,
            args.candidate_desktop,
            args.reference_mobile,
            args.candidate_mobile,
            args.threshold,
        )

    parser = build_parser()
    args = parser.parse_args(argv)

    if args.command == "compare":
        masks = MASKS.get(args.route, {}).get(args.viewport, [])
        result = compare(args.reference, args.candidate, masks, threshold=args.threshold)
        result["route"] = args.route
        result["viewport"] = args.viewport
        print(json.dumps(result, indent=2))
        return 0 if result["passed"] else 1

    if args.command == "capture":
        url = f"{args.origin.rstrip('/')}{args.route}"
        path = capture_page(url, args.viewport, args.output)
        print(json.dumps({"path": str(path), "url": url, "viewport": args.viewport}))
        return 0

    if args.command == "gate":
        result = run_gate(
            args.reference_origin, args.candidate_origin, args.output_dir, args.threshold
        )
        print(json.dumps(result.to_dict(), indent=2))
        return 0 if result.passed else 1

    parser.print_help()
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
