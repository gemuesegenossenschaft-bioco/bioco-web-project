"""Behavioural tests for the real `bioco-navigation.js`.

These execute the real script in Node against a DOM stub. Two behaviour
families:

1. Sticky navigation utility-row auto-hide: the stub reproduces the production
   layout coupling — collapsing the utility row shrinks the document, so the
   browser shifts `scrollY` down over the CSS transition. A handler that reads
   that shift as a user scroll-up oscillates forever.
2. Mobile menu toggle: open/close/Escape/link-click with the accessible
   attribute contract the server-rendered shell promises.
"""

import json
import shutil
import subprocess
from pathlib import Path

import pytest

REPO = Path(__file__).resolve().parents[1]
HARNESS = REPO / "tests" / "js" / "navigation-scroll-harness.mjs"


@pytest.fixture(scope="module")
def scenarios():
    node = shutil.which("node")
    if not node:
        pytest.skip("node is required for the navigation scroll simulation")
    result = subprocess.run(
        [node, str(HARNESS)],
        capture_output=True,
        text=True,
        check=False,
        cwd=REPO,
    )
    assert result.returncode == 0, result.stderr
    return json.loads(result.stdout)


def test_wheel_down_collapses_once_and_stays_collapsed(scenarios):
    """The reported defect: one wheel-down of 700px on /aktuelles."""
    case = scenarios["wheel_down_700"]
    assert case["hidden"] is True
    # After the transition has fully run (>1.2s of frames) the row is gone,
    # not stuck at ~69px because the class kept flipping.
    assert case["utility_height"] == 0
    # Exactly one state change: hide. No oscillation.
    assert case["class_changes"] == 1


def test_slow_scrolling_accumulates_into_a_hide(scenarios):
    case = scenarios["slow_accumulates"]
    assert case["hidden"] is True
    assert case["class_changes"] == 1


def test_genuine_scroll_up_reveals_the_utility_row(scenarios):
    case = scenarios["genuine_up_reveals"]
    assert case["hidden_after_down"] is True
    assert case["hidden"] is False
    assert case["utility_height"] == 70


def test_returning_to_the_top_reveals_the_utility_row(scenarios):
    case = scenarios["top_reveals"]
    assert case["scroll_y"] == 0
    assert case["hidden"] is False
    assert case["utility_height"] == 70


def test_focus_within_utility_row_keeps_it_visible(scenarios):
    case = scenarios["focus_within_keeps_visible"]
    assert case["hidden"] is False
    assert case["utility_height"] == 70
    assert case["contains_focused"] is True
    assert case["contains_outside"] is False


def test_reduced_motion_instant_collapse_is_stable(scenarios):
    case = scenarios["reduced_motion"]
    assert case["hidden_after_down"] is True
    assert case["class_changes_after_down"] == 1
    assert case["hidden_after_up"] is False


def test_mobile_hidden_utility_row_causes_no_layout_feedback(scenarios):
    case = scenarios["mobile_no_layout_shift"]
    assert case["scroll_y"] == 700
    assert case["class_changes"] <= 1


def test_scroll_listener_stays_passive_and_single(scenarios):
    case = scenarios["listener"]
    assert case["count"] == 1
    assert case["passive"] is True


def test_duplicate_shell_markup_controls_only_the_first_shell(scenarios):
    """Templates render one authoritative shell; stray duplicates are inert."""
    case = scenarios["duplicate_shell"]
    # Still one controller: a single passive scroll listener, not one per shell.
    assert case["listener_count"] == 1
    assert case["listener_passive"] is True
    # The duplicate is never written to — no competing controller.
    assert case["decoy_touches"] == 0
    # The first shell behaves exactly as in the single-shell case.
    assert case["hidden_after_down"] is True
    assert case["class_changes_after_down"] == 1
    assert case["hidden_after_up"] is False
    assert case["utility_height"] == 70
    assert case["total_class_changes"] == 2


# ---------------------------------------------------------------------------
# Mobile menu toggle (same real script, menu stubs)
# ---------------------------------------------------------------------------


def test_menu_toggle_opens_the_menu_with_a11y_state_and_focus(scenarios):
    case = scenarios["menu_toggle_open"]
    assert case["is_open"] is True
    assert case["aria_expanded"] == "true"
    assert case["aria_label"] == "Menü schliessen"
    assert case["focus_target"] == "A"


def test_second_toggle_click_closes_and_restores_the_open_label(scenarios):
    case = scenarios["menu_toggle_close"]
    assert case["is_open"] is False
    assert case["aria_expanded"] == "false"
    assert case["aria_label"] == "Menü öffnen"


def test_escape_closes_the_menu_and_returns_focus_to_the_toggle(scenarios):
    case = scenarios["menu_escape_closes"]
    assert case["is_open"] is False
    assert case["aria_expanded"] == "false"
    assert case["focus_target"] == "BUTTON"


def test_clicking_a_menu_link_closes_the_menu(scenarios):
    case = scenarios["menu_link_click_closes"]
    assert case["is_open"] is False
    assert case["aria_expanded"] == "false"
    assert case["aria_label"] == "Menü öffnen"


def test_primary_nav_without_toggle_stays_completely_inert(scenarios):
    case = scenarios["menu_without_toggle_inert"]
    assert case["toggle_click_listeners"] == 0
    assert case["nav_keydown_listeners"] == 0
    assert case["link_click_listeners"] == 0
    assert case["is_open"] is False
