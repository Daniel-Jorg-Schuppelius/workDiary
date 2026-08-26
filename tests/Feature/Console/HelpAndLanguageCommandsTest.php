<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpAndLanguageCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 (MVP-725): Hilfe- und Übersetzungs-Werkzeuge.
 *
 * `help:coverage` und `lang:check` sind CI-Gates (Exit ≠ 0 bei Befunden),
 * `help:reindex` schreibt den Suchindex, `lang:sync` legt Übersetzungsdateien
 * an. Letzteres wird bewusst NUR auf seinen beiden schreibfreien Pfaden
 * getestet — ein `--fill`-Lauf würde die echten Sprachdateien des Repos
 * umschreiben.
 */
class HelpAndLanguageCommandsTest extends TestCase {
    use RefreshDatabase;

    // ── help:coverage ────────────────────────────────────────────────────

    public function test_help_coverage_passes_when_every_page_is_mapped(): void {
        config([
            'help-topics.routes' => [],
            // Alle Seiten als bewusste Ausnahme → keine unbelegten Seiten.
            'help-topics.coverage_exceptions' => ['*'],
            'app.available_locales' => ['de'],
        ]);

        $this->artisan('help:coverage')
            ->expectsOutputToContain('Hilfe-Abdeckung vollständig')
            ->assertExitCode(0);
    }

    public function test_help_coverage_fails_for_a_topic_without_source_file(): void {
        config([
            'help-topics.routes' => ['dashboard' => 'gibt-es-nicht'],
            'help-topics.coverage_exceptions' => ['*'],
            'app.available_locales' => ['de'],
        ]);

        $this->artisan('help:coverage')
            ->expectsOutputToContain('Registriertes Topic ohne de-Datei: gibt-es-nicht')
            ->expectsOutputToContain('Hilfe-Abdeckung unvollständig')
            ->assertExitCode(1);
    }

    public function test_help_coverage_fails_for_a_missing_translation(): void {
        config([
            'help-topics.routes' => [],
            'help-topics.coverage_exceptions' => ['*'],
            // Eine Sprache, die es unter resources/help/ nicht gibt.
            'app.available_locales' => ['de', 'zz'],
        ]);

        $this->artisan('help:coverage')
            ->expectsOutputToContain('Fehlende Übersetzung (zz)')
            ->assertExitCode(1);
    }

    // ── help:reindex ─────────────────────────────────────────────────────

    public function test_help_reindex_fills_the_topic_index(): void {
        $this->artisan('help:reindex')
            ->expectsOutputToContain('Hilfe-Topics aktualisiert')
            ->assertExitCode(0);

        $this->assertGreaterThan(0, DB::table('help_topics')->count());
    }

    public function test_help_reindex_is_idempotent(): void {
        $this->artisan('help:reindex')->assertExitCode(0);
        $first = DB::table('help_topics')->count();

        $this->artisan('help:reindex')
            ->expectsOutputToContain('0 entfernt')
            ->assertExitCode(0);

        $this->assertSame($first, DB::table('help_topics')->count());
    }

    // ── lang:check ───────────────────────────────────────────────────────

    public function test_lang_check_confirms_full_parity(): void {
        // Das Gate ist Teil von `composer qa` — wird es rot, ist der
        // Übersetzungsbestand tatsächlich unvollständig.
        $this->artisan('lang:check')
            ->expectsOutputToContain('Übersetzungen vollständig')
            ->assertExitCode(0);
    }

    // ── lang:sync ────────────────────────────────────────────────────────

    public function test_lang_sync_without_flags_changes_nothing(): void {
        $before = $this->langFingerprint();

        $this->artisan('lang:sync')
            ->expectsOutputToContain('Weder --fill noch --prune gesetzt')
            ->assertExitCode(0);

        $this->assertSame($before, $this->langFingerprint(), 'Ohne Flags darf keine Sprachdatei geschrieben werden.');
    }

    public function test_lang_sync_refuses_the_source_language(): void {
        $before = $this->langFingerprint();

        $this->artisan('lang:sync --locale=de --fill --prune')
            ->expectsOutputToContain('de ist die Quellsprache')
            ->assertExitCode(0);

        $this->assertSame($before, $this->langFingerprint(), 'de ist die Quelle und darf nie generiert werden.');
    }

    /** Inhalts-Fingerabdruck der JSON-Sprachdateien (Schreibnachweis). */
    private function langFingerprint(): string {
        $parts = [];
        foreach (glob(base_path('lang/*.json')) ?: [] as $file) {
            $parts[] = basename($file) . ':' . (string) md5_file($file);
        }
        sort($parts);

        return implode('|', $parts);
    }
}
