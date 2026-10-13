"""Run the native media/rich-text preservation checks in the normal PR gate."""
import subprocess
from pathlib import Path


def test_media_rich_native_preview_preserves_authored_content_and_controls():
    root = Path(__file__).parents[1]
    result = subprocess.run(
        ['php', 'tests/editorial-presentation-preview.php'],
        cwd=root, capture_output=True, text=True, check=True,
    )
    assert result.stderr == ''
    assert result.stdout.strip() == 'editorial-presentation-preview: passed'
