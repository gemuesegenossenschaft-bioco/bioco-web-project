# PRD: Intranet-Signup in den /bioco-werden-Flow integrieren (ohne Intranet-Änderung)

> **Status:** Entwurf zur Diskussion · **Bezug:** Issue #74, Epic #50 (Track D) · **Zielsystem:** bioco.ch (Next.js, später WordPress) · **Grundsatz:** Das Intranet (`intranet.bioco.ch`, Django) bleibt System-of-Record und wird NICHT verändert.

**TL;DR (English):** Make `bioco.ch/bioco-werden` the on-brand front door for membership signup while the Django intranet at `intranet.bioco.ch/my/signup/` stays the unchanged system of record. The public site already renders the fields (`MembershipForm`); we submit them through a server-side bioco.ch adapter (`/api/forms/membership`) that reproduces a browser's behaviour against the intranet form — first `GET` the intranet page to obtain the `csrftoken` cookie plus the hidden `csrfmiddlewaretoken`, then `POST` the mapped fields with both. Turnstile is verified on the bioco.ch side; if the intranet's CSRF/session/captcha makes a direct forward unreliable, we fall back to the notify/forward adapter from #50. No intranet code changes.

---

## Problem Statement

Wer heute Mitglied werden will, wird aus dem gepflegten bioco.ch-Design in die Intranet-Anmeldung geschickt — ein Bruch in Optik und Vertrauen. Die Anmeldung soll durchgängig auf `bioco.ch/bioco-werden` passieren, on-brand, in einem Fluss. Gleichzeitig darf am Intranet **nichts** geändert werden: keine API, kein CORS, kein neues Formular. Das Intranet bleibt die einzige Quelle der Wahrheit für Mitgliedschaften.

## Solution

`bioco-werden` führt (wie heute über den `AboRechner`/`pricing_calculator`) in die bestehende, mehrstufige `MembershipForm`. Der Absenden-Schritt geht an einen **serverseitigen Adapter** in Next.js (`/api/forms/membership`), der die Anmeldung an den Intranet-Endpunkt weiterreicht. Weil Django das Formular mit CSRF schützt und ein Cross-Origin-POST aus dem Browser durch CSRF+CORS scheitert, macht der Adapter serverseitig genau das, was ein Browser täte:

1. `GET https://intranet.bioco.ch/my/signup/` → `csrftoken`-Cookie und das versteckte `csrfmiddlewaretoken`-Feld auslesen.
2. Die gemappten Felder als `POST` an denselben Endpunkt senden, mit `Cookie: csrftoken=…`, `Referer`-Header und `csrfmiddlewaretoken` im Body.
3. Antwort auswerten (Redirect/Erfolgsseite = ok; Formular mit Fehlern = Feldfehler zurück an die UI).

Kein Intranet-Change — nur Automatisierung des Browserverhaltens. Wenn CSRF/Session/Captcha den direkten Forward blockieren, greift der **Fallback** aus #50: der Adapter speichert die Anmeldung und benachrichtigt/leitet sie per E-Mail (SMTP `mail.bioco.ch:465`) an die zuständige Stelle weiter — die manuelle Übertragung ins Intranet bleibt möglich, ohne die UX zu ändern.

## User Stories

1. Als Interessent:in möchte ich mich vollständig auf bioco.ch anmelden, ohne auf eine fremd aussehende Seite zu wechseln, damit der Beitritt vertrauenswürdig und aus einem Guss wirkt.
2. Als Interessent:in möchte ich meine im `AboRechner` gewählte Abo-Variante beim Anmeldeformular vorausgefüllt sehen, damit ich nichts doppelt eingeben muss.
3. Als Interessent:in möchte ich bei Fehleingaben klare, deutsche Feldfehler direkt im bioco.ch-Formular sehen, damit ich die Anmeldung korrigieren und abschliessen kann.
4. Als Vorstand/Admin möchte ich, dass jede Anmeldung zuverlässig im Intranet (System-of-Record) landet — direkt oder über einen dokumentierten Fallback — damit keine Anmeldung verloren geht.
5. Als Betreiber möchte ich, dass am Intranet nichts geändert werden muss, damit die Integration ohne Django-Deploy und ohne Risiko für das Intranet auskommt.
6. Als Datenschutzverantwortliche:r möchte ich, dass die Statuten-/Betriebsreglement-Bestätigung Pflicht ist und Turnstile serverseitig geprüft wird, damit nur gültige, bestätigte Anmeldungen durchgehen.

## Implementation Decisions

**Adapter (`frontend/app/api/forms/membership/route.ts`)** — serverseitig, drei Phasen:
- *Prime:* `GET` auf die Intranet-Signup-URL; `set-cookie` (`csrftoken`) und `csrfmiddlewaretoken` (verstecktes Input) parsen.
- *Forward:* `POST` mit `application/x-www-form-urlencoded`, gesetztem `Cookie`, `Referer` und `csrfmiddlewaretoken`; Redirects nicht automatisch folgen, sondern Status/Location auswerten.
- *Interpret:* 302/200-Erfolgsseite → `{ ok: true }`; 200 mit Formularfehlern → Fehler extrahieren und als Feldfehler zurückgeben.

**Feld-Mapping (bioco.ch → Intranet)** — die exakten Intranet-Feldnamen müssen erst per eingeloggter Erhebung bestätigt werden (siehe Further Notes); Struktur:

| bioco.ch (`MembershipForm`) | Intranet-Feld (zu bestätigen) |
|---|---|
| firstName / lastName | first_name / last_name |
| email | email |
| phone | phone |
| address / zip / city | street / postal_code / city |
| membershipType / aboType / additionalShares | membership_type / abo / shares |
| depot | depot |
| paymentType | payment_interval |
| commitmentAccepted / privacyAccept | terms / statutes ack |
| (weitere: preferredDays/-Times, activityAreas, zusatzabos) | optionale Intranet-Felder / Notizfeld |

Das Mapping lebt als reine Funktion in `frontend/lib/membership.ts` (baut auf D.1-Validierung auf), damit es unit-testbar ist.

**Turnstile:** Der bereits vorhandene serverseitige Turnstile-Check läuft VOR dem Forward; ohne gültiges Token kein Forward.

**Fehlerbehandlung & Idempotenz:** Netzwerk-/CSRF-Fehler → 502 mit generischer DE-Meldung + Log; doppelte Absendungen durch ein kurzlebiges Idempotenz-Token (Formular-Nonce) abfangen, damit ein Doppelklick nicht zwei Intranet-Einträge erzeugt.

**Fallback (#50):** Schlägt der direkte Forward strukturell fehl (CSRF-Rotation, Session-Zwang, Captcha im Intranet), schaltet der Adapter auf Notify/Forward um (Speichern + E-Mail an die zuständige Adresse), ohne die Frontend-UX zu ändern.

## Testing Decisions

- **Contract-Test** für das Feld-Mapping: `MembershipForm`-Daten → erwarteter Intranet-Payload (reine Funktion, kein Netz).
- **Adapter-Test gegen einen Mock-Intranet-Endpoint**: prüft die CSRF-Prime-→-Forward-Sequenz (Cookie + `csrfmiddlewaretoken` werden korrekt gesetzt), Erfolg- und Fehler-Antworten.
- **e2e**: Statuten-/Betriebsreglement-Bestätigung ist Pflicht (Absenden ohne Ack schlägt fehl) — deckt #50 D.3 ab.
- Prior art: bestehende Form-Route-Tests (`forms-captcha-routes.test.ts`) und `membership-validation.test.ts`.

## Out of Scope

- **Jegliche Änderung an `intranet.bioco.ch`.** Keine neue Intranet-API, kein CORS, kein Formular-Umbau. Das Intranet bleibt System-of-Record.
- Mitglieder-Verwaltung/Buchhaltung (bleibt im Intranet).
- Der Wechsel des Frontends zu WordPress (separate Migration, Epic #73) — dieser Adapter wird dort als mu-plugin-Handler reimplementiert, die Logik bleibt gleich.

## Further Notes

- **Offene Abhängigkeit:** Die exakten Intranet-Formularfelder (Namen, Pflicht, Optionen, CSRF-/Captcha-Verhalten) müssen per **eingeloggtem** Zugriff auf `intranet.bioco.ch/my/signup/` erhoben werden — die Seite liefert unauthentifiziert 403. Bis dahin ist die Mapping-Tabelle vorläufig.
- **Risiko:** Django kann den CSRF-Mechanismus oder ein Captcha jederzeit anziehen; der Fallback (#50 Notify/Forward) macht die Integration davon unabhängig robust.
- Referenzen: Issue #74 (Optionen), Epic #50 (Track D, `lib/membership.ts` D.1, `/api/forms/membership` D.2, e2e D.3).
