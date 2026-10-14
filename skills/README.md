# Bioco agent skills

These are the canonical, portable sources. Each package has a `SKILL.md` with only `name` and `description` frontmatter. Tool-specific installation copies belong outside this source tree; do not commit generated skill-install directories.

- [bioco-ticket](bioco-ticket/SKILL.md): write or pick up one bounded engineering ticket, then leave evidence another agent can use.
- [bioco-release](bioco-release/SKILL.md): prepare a concrete release packet and execute only the approved production action.

Load the relevant skill explicitly when the agent does not discover this directory. Both read current repository instructions at runtime. Installing a skill does not enable a release feature or prove host behavior.

The workflows adapt Matt Pocock, Theo and poteto's linked primary sources. Bioco's approval and editorial preservation rules come from this project's decisions.
