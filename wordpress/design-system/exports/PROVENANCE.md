# Export provenance

All three artifacts are byte-for-byte downloads from the staging Divi portability
surfaces, captured 2026-09-16 with an authenticated administrator browser session
via the real export UI (no REST replay, no hand assembly, no sanitizing).

Common metadata:

- Source: https://staging.bioco.ch (Divi theme 5.11.0, ET_BUILDER_VERSION 5.11.0)
- Captured: 2026-09-16 22:09-22:12 UTC
- Repository commit at capture: 5fa3519dd4ad01fb5ba9eb82ec3fa6e97c7c6326
  (deployed to staging as release marker 5fa3519dd4ad01fb5ba9eb82ec3fa6e97c7c6326)
- Capturing operator: OpenCode Zen (opencode/glm-5.3-flash) release run
- Secret scan: inspected for api_key / et_license / license / token / password /
  secret markers before commit; none found. `et_google_api_settings` in the theme
  options surface contains only font/map toggles (vendor strips api_key).

| Artifact | Export surface | SHA256 |
| --- | --- | --- |
| variables.json | Visual Builder → Variablen manager → Import & Export → "Variablen exportieren" ("Alle Variablen.json") | b032383e02a9d00b6793c57275f9e2234cf4432492b1c7217a24d4795d921046 |
| presets.json | Visual Builder → Voreinstellungen manager → Import & Export → "Presets exportieren" ("Alle Presets.json") | ecf0928d5345922490867d795b1e668b8bc7d7c145f832d08aef1f32c7fcb820 |
| theme-builder.json | Divi → Theme Builder → Portability → Export ("Divi-Theme-Builder-Templates.json") | dd26ec061a1b9669ba311a11421ce344fc7bf712bf9db2651f030682a992c987 |

The Theme Builder export contains the three shared layouts (IDs 299 header,
301 body, 303 footer) inside the default "Standard-Website-Template". The
theme options (epanel) export was also captured as evidence but is not part of
the release gate; the Divi 5 global variables/presets are independent of it.
