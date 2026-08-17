#!/usr/bin/env python3
"""Measured screenshot parity gate for the bioco.ch homepage."""

import argparse
import json
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw


DEFAULT_THRESHOLD = 0.95

DESKTOP_MASKS = [
    (0, 1846, 1440, 3828),  # CMS-fed Beiträge / Events / Schnuppertage
    (0, 4108, 1440, 6563),  # CMS-fed Aktuelles feed
]

MOBILE_MASKS = [
    (4, 40, 386, 612),      # responsive hero bitmap crop
    (46, 1190, 344, 1505),  # CMS feature bitmap
    (46, 2130, 344, 2445),  # CMS feature bitmap
    (0, 2512, 390, 6090),   # CMS-fed Beiträge / Events / Schnuppertage
    (0, 6543, 390, 11597),  # CMS-fed Aktuelles feed
]


def compare(reference_path, candidate_path, masks):
    reference = Image.open(reference_path).convert("RGB")
    candidate = Image.open(candidate_path).convert("RGB")
    width = min(reference.width, candidate.width)
    height = min(reference.height, candidate.height)
    reference_crop = reference.crop((0, 0, width, height))
    candidate_crop = candidate.crop((0, 0, width, height))

    active = Image.new("L", (width, height), 255)
    draw = ImageDraw.Draw(active)
    for left, top, right, bottom in masks:
        draw.rectangle(
            (max(0, left), max(0, top), min(width, right), min(height, bottom)),
            fill=0,
        )

    difference = ImageChops.difference(reference_crop, candidate_crop)
    channel_means = []
    for channel in difference.split():
        histogram = channel.histogram(mask=active)
        count = sum(histogram)
        channel_means.append(
            sum(value * frequency for value, frequency in enumerate(histogram)) / count
            if count else 255.0
        )

    score = 1.0 - sum(channel_means) / (3 * 255)
    height_accuracy = min(reference.height, candidate.height) / max(reference.height, candidate.height)
    width_accuracy = min(reference.width, candidate.width) / max(reference.width, candidate.width)
    return {
        "score": round(score, 6),
        "height_accuracy": round(height_accuracy, 6),
        "width_accuracy": round(width_accuracy, 6),
        "overall": round(min(score, height_accuracy, width_accuracy), 6),
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--reference-desktop", type=Path, required=True)
    parser.add_argument("--candidate-desktop", type=Path, required=True)
    parser.add_argument("--reference-mobile", type=Path, required=True)
    parser.add_argument("--candidate-mobile", type=Path, required=True)
    parser.add_argument("--threshold", type=float, default=DEFAULT_THRESHOLD)
    args = parser.parse_args()

    results = {
        "desktop": compare(args.reference_desktop, args.candidate_desktop, DESKTOP_MASKS),
        "mobile": compare(args.reference_mobile, args.candidate_mobile, MOBILE_MASKS),
        "threshold": args.threshold,
    }
    results["passed"] = all(
        results[viewport]["overall"] >= args.threshold
        for viewport in ("desktop", "mobile")
    )
    print(json.dumps(results, indent=2))
    raise SystemExit(0 if results["passed"] else 1)


if __name__ == "__main__":
    main()
