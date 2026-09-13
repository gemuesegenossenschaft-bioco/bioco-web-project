# ADR 0001: Redaktionelle Inhalte bei Staging-Releases erhalten

2026-09-13. Angenommen für #179.

## Entscheidung

Ein Staging-Release aktualisiert den Projektcode. Der bisher erzwungene Inhaltsimport entfällt, weil er Änderungen aus Divi und wp-admin mit dem Seed-Stand überschrieb.

Der Release sichert weiterhin die Datenbank, synchronisiert die Projektdateien und leert den Cache. Danach prüft `wp bioco verify --runtime` die benötigten Seiten. Die HTTP-Prüfung aller 22 Routen und der Release-Marker bleiben erhalten.

Die Laufzeitprüfung akzeptiert geänderte Inhalte. Sie verlangt gültige Block-Kommentare und insgesamt sichtbare Ausgabe pro Seite. Dafür verwendet sie den WordPress-Parser und den aktiven Divi-Renderer im jeweiligen Seitenkontext. Beschädigte Kommentare und Renderer-Fehler lassen die Prüfung scheitern. Dekorative Abschnitte und Speziallayouts mit direkten Spalten bleiben zulässig.

## Inhaltsimport

`wp bioco import` bleibt eine gesonderte Operation. Ohne `--apply` zeigt der Befehl eine Vorschau. Mit `--apply` schützt er bereits vorhandene Inhalte; `--apply --force` überschreibt sie ausdrücklich. Vor diesem Aufruf ist das Backup zu prüfen. `--force` ohne `--apply` zeigt die geplanten Überschreibungen, ohne Daten oder Berichte zu speichern.

`wp bioco verify` vergleicht weiterhin den importierten Stand mit den Seed-Dateien und dient der Abnahme eines Inhaltsimports.

## Abwägung und Grenzen

Nur `--force` zu entfernen genügt nicht: Der bisherige Seed-Abgleich würde redaktionelle Änderungen weiterhin ablehnen. Ein automatischer Abgleich einzelner redaktioneller Felder ist für einen Code-Release nicht erforderlich.

Die Laufzeitprüfung erkennt keine einzelne fehlende Komponente, solange die Seite noch gültigen, sichtbaren Inhalt liefert. Die Vollständigkeit wird beim Inhaltsimport und in der redaktionellen Abnahme geprüft. Ein Vergleich der Daten vor und nach dem Release ergänzt die automatisierten Tests zum Schutz der Inhalte.
