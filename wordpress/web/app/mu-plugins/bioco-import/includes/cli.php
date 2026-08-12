<?php
/**
 * WP-CLI surface for the content importer.
 * ============================================================================
 * Deliberately thin: this file only parses flags, enforces the safety rules,
 * and calls the plain functions in the sibling includes. All real logic lives
 * there so it stays reviewable without a WP-CLI bootstrap.
 *
 * Safety model, in order of importance:
 *  1. DRY-RUN IS THE DEFAULT. Nothing is written unless --apply is passed.
 *  2. NO CLOBBER. An existing page with non-empty content is reported and
 *     skipped, never overwritten, unless --force is passed explicitly.
 *  3. IDEMPOTENT. Running twice with --apply produces the same site state as
 *     running once; the second run reports "unchanged" rows.
 *  4. FAIL LOUD. Any error row makes the command exit non-zero, so a CI step
 *     or a shell operator cannot mistake a partial import for a clean one.
 */

if (!defined('ABSPATH')) exit;

class Bioco_Import_CLI_Command {

    /**
     * Importiert die bioco-Inhalte aus den Seed-Dateien in WordPress.
     *
     * Standard ist ein Probelauf (dry-run): es wird NICHTS geschrieben, nur
     * berichtet, was passieren wuerde. Erst --apply schreibt.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Schreibt die Aenderungen wirklich. Ohne dieses Flag laeuft nur ein Probelauf.
     *
     * [--force]
     * : Ueberschreibt auch Seiten, die bereits eigenen Inhalt haben. Nur zusammen
     * mit --apply sinnvoll. Ohne --force werden solche Seiten uebersprungen.
     *
     * [--only=<slugs>]
     * : Nur diese Seiten importieren, Slugs mit Komma getrennt (z. B. home,kontakt).
     *
     * [--seed-dir=<pfad>]
     * : Verzeichnis mit den Seed-JSON-Dateien. Standard: wordpress/content-seed.
     *
     * [--events-json=<quelle>]
     * : Datei oder URL mit dem Events-Export, z. B.
     * https://cms.bioco.ch/api/content/events. Ohne Angabe wird der
     * Events-Import uebersprungen.
     *
     * [--groups-json=<quelle>]
     * : Datei oder URL mit dem Gruppen-Export. Ohne Angabe wird der
     * Gruppen-Import uebersprungen.
     *
     * [--skip-collections]
     * : Events und Gruppen gar nicht anfassen (nur Seiten importieren).
     *
     * [--skip-site-wiring]
     * : Startseite, Permalinks und Hauptmenue nicht setzen.
     *
     * [--collections-only]
     * : Nur Events/Gruppen importieren. Seiten und Site-Wiring bleiben unberührt.
     *
     * [--no-html-report]
     * : Keinen HTML-Bericht unter wp-content/bioco-import-log/ schreiben.
     *
     * ## EXAMPLES
     *
     *     # Probelauf ueber alle Seiten, schreibt nichts
     *     wp bioco import
     *
     *     # Eine einzelne Seite testen
     *     wp bioco import --only=kontakt
     *
     *     # Wirklich importieren, inklusive Events vom alten CMS
     *     wp bioco import --apply --events-json=https://cms.bioco.ch/api/content/events
     *
     * @when after_wp_load
     */
    public function import($args, $assoc_args) {
        $apply = (bool) WP_CLI\Utils\get_flag_value($assoc_args, 'apply', false);
        $explicitDryRun = (bool) WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        if ($apply && $explicitDryRun) {
            WP_CLI::error('--apply und --dry-run widersprechen sich. Bitte nur eines angeben.');
        }
        $mode = $apply ? 'apply' : 'dry-run';
        $force = (bool) WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
        $collectionsOnly = (bool) WP_CLI\Utils\get_flag_value($assoc_args, 'collections-only', false);

        // --force only means something when we are actually writing. Silently
        // accepting it in a dry run would teach the operator that the flag is
        // harmless, and the next run with --apply would then overwrite content.
        if ($force && !$apply) {
            WP_CLI::error('--force wirkt nur zusammen mit --apply. Probelauf bitte ohne --force starten.');
        }

        $seedDir = isset($assoc_args['seed-dir']) && $assoc_args['seed-dir'] !== ''
            ? $assoc_args['seed-dir']
            : BIOCO_IMPORT_DEFAULT_SEED_DIR;
        $only = $this->parse_only($assoc_args);

        $this->assert_acf_available();

        try {
            $seeds = bioco_import_load_seeds($seedDir, $only);
        } catch (RuntimeException $e) {
            WP_CLI::error($e->getMessage());
            return;
        }

        WP_CLI::log(sprintf(
            '%s: %d Seed-Datei(en) aus %s%s',
            $apply ? 'IMPORT (schreibend)' : 'PROBELAUF (schreibt nichts)',
            count($seeds),
            $seedDir,
            $force ? ' — mit --force, bestehende Inhalte werden ueberschrieben' : ''
        ));

        $report = bioco_import_report_new();

        if (!$collectionsOnly) {
            bioco_import_run($seeds, $mode, $force, $report);
        }

        if (!WP_CLI\Utils\get_flag_value($assoc_args, 'skip-collections', false)) {
            bioco_import_run_collections(
                isset($assoc_args['events-json']) ? $assoc_args['events-json'] : '',
                isset($assoc_args['groups-json']) ? $assoc_args['groups-json'] : '',
                $mode,
                $force,
                $report
            );
        }

        if (!$collectionsOnly && !WP_CLI\Utils\get_flag_value($assoc_args, 'skip-site-wiring', false)) {
            bioco_import_run_site_wiring($seeds, $mode, $force, $report);
        }

        $this->finish($report, $mode, $force, $assoc_args, $apply);
    }

    /**
     * Prueft, ob der importierte Stand mit den Seed-Dateien uebereinstimmt.
     *
     * Reiner Lesevorgang: schreibt nie etwas. Gedacht als Abnahme-Gate vor dem
     * Umschalten und als Regressionstest nach Divi-Aenderungen.
     *
     * ## OPTIONS
     *
     * [--only=<slugs>]
     * : Nur diese Seiten pruefen, Slugs mit Komma getrennt.
     *
     * [--seed-dir=<pfad>]
     * : Verzeichnis mit den Seed-JSON-Dateien. Standard: wordpress/content-seed.
     *
     * [--no-html-report]
     * : Keinen HTML-Bericht schreiben.
     *
     * ## EXAMPLES
     *
     *     wp bioco verify
     *     wp bioco verify --only=home
     *
     * @when after_wp_load
     */
    public function verify($args, $assoc_args) {
        $seedDir = isset($assoc_args['seed-dir']) && $assoc_args['seed-dir'] !== ''
            ? $assoc_args['seed-dir']
            : BIOCO_IMPORT_DEFAULT_SEED_DIR;
        $only = $this->parse_only($assoc_args);

        $this->assert_acf_available();

        try {
            $seeds = bioco_import_load_seeds($seedDir, $only);
        } catch (RuntimeException $e) {
            WP_CLI::error($e->getMessage());
            return;
        }

        WP_CLI::log(sprintf('PRUEFUNG: %d Seed-Datei(en) aus %s', count($seeds), $seedDir));

        $report = bioco_import_report_new();
        bioco_import_run_verify($seeds, $report);

        $this->finish($report, 'verify', false, $assoc_args, false);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function parse_only(array $assoc_args) {
        if (empty($assoc_args['only'])) return [];
        $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $assoc_args['only'])), 'strlen'));
        if (!$slugs) {
            WP_CLI::error('--only wurde angegeben, enthaelt aber keinen Slug.');
        }
        return $slugs;
    }

    // The ACF field keys are resolved through ACF's own API (see
    // includes/acf-fields.php). Without ACF every single block would fail with
    // the same error, producing a useless wall of rows — so refuse up front.
    private function assert_acf_available() {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_get_fields')) {
            WP_CLI::error(
                'Es ist kein ACF-kompatibles Plugin aktiv. Der Importer loest die Feld-Keys ueber die '
                . 'ACF-API selbst auf und kann sonst keine gueltigen Block-Daten schreiben. '
                . 'Zuerst: wp plugin activate secure-custom-fields '
                . '(Secure Custom Fields, frei und GPL, deckt Repeater/Blocks ab). '
                . 'ACF Pro funktioniert ebenfalls: wp plugin activate advanced-custom-fields-pro'
            );
        }
    }

    // Shared tail for both subcommands: print, persist, then set the exit code.
    private function finish(array $report, $mode, $force, array $assoc_args, $apply) {
        bioco_import_report_print_cli($report, $mode, $force);

        if (!WP_CLI\Utils\get_flag_value($assoc_args, 'no-html-report', false)) {
            $htmlPath = bioco_import_report_write_html($report, $mode, $force);
            if ($htmlPath) {
                WP_CLI::log('HTML-Bericht: ' . $htmlPath);
            }
        }
        $logPath = bioco_import_report_write_log($report, $mode, $force);
        if ($logPath) {
            WP_CLI::log('Log: ' . $logPath);
        }

        if (bioco_import_report_has_failures($report)) {
            WP_CLI::error('Es gibt Fehler oder Abweichungen — siehe die Zeilen oben. Es wurde nichts weiter geaendert.');
            return;
        }

        if ($mode === 'dry-run') {
            WP_CLI::success('Probelauf ohne Fehler. Zum wirklichen Schreiben denselben Befehl mit --apply erneut ausfuehren.');
            return;
        }
        if ($mode === 'verify') {
            WP_CLI::success('Der importierte Stand stimmt mit den Seed-Dateien ueberein.');
            return;
        }
        WP_CLI::success('Import abgeschlossen.');
    }
}
