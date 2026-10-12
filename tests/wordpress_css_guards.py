"""Allow only the two reviewed sticky-ancestor inline-style overrides."""

STICKY_ANCESTOR_OVERRIDES = (
    "body.et-db:has(.bioco-global-header) { overflow-x: clip !important; }",
    ".et-db #page-container:has(.bioco-global-header) { overflow: visible !important; }",
)


def ordinary_important_count(css):
    for rule in STICKY_ANCESTOR_OVERRIDES:
        assert css.count(rule) == 1, "Sticky exception must retain its exact scope and declaration"
        css = css.replace(rule, "")
    return css.count("!important")
