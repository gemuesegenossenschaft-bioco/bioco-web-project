import importlib.util
from pathlib import Path

from PIL import Image


ROOT = Path(__file__).parents[1]
SCRIPT = ROOT / "wordpress/scripts/visual-parity.py"


def _module():
    spec = importlib.util.spec_from_file_location("visual_parity", SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def test_visual_parity_gate_defaults_to_ninety_percent(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    Image.new("RGB", (20, 20), "#f5f1e8").save(reference)
    Image.new("RGB", (20, 20), "#f5f1e8").save(candidate)

    result = parity.compare(reference, candidate, masks=[])
    assert parity.DEFAULT_THRESHOLD == 0.90
    assert result["score"] == 1.0
    assert result["height_accuracy"] == 1.0


def test_visual_parity_masks_only_declared_volatile_rectangles(tmp_path):
    parity = _module()
    reference = tmp_path / "reference.png"
    candidate = tmp_path / "candidate.png"
    Image.new("RGB", (20, 20), "white").save(reference)
    changed = Image.new("RGB", (20, 20), "white")
    for x in range(5):
        for y in range(5):
            changed.putpixel((x, y), (0, 0, 0))
    changed.save(candidate)

    assert parity.compare(reference, candidate, masks=[])["score"] < 1.0
    assert parity.compare(reference, candidate, masks=[(0, 0, 5, 5)])["score"] == 1.0
