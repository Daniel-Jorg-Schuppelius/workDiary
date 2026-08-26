<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledScanCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Domain\DomainProviderConnection;
use App\Models\HelpView;
use App\Services\Security\OsvAdvisoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue};
use Mockery;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 (MVP-725): die vom Scheduler getriebenen Scan- und
 * Versandläufe. Sie standen bisher nur in der frozen EXPECTED-Liste des
 * SchedulerRegistrationTest — dass sie *laufen*, prüfte niemand.
 *
 * Muster je Kommando: Lauf mit Minimaldaten (Null-Ergebnis, Exit 0), ein
 * Guard-/Fehlerpfad und — wo vorhanden — ein nachweisbarer Seiteneffekt.
 * Ausgehende Verbindungen bleiben aus: Http/Queue sind gefaked, und die
 * einzige Verbindungs-abhängige Fassade (OSV) wird als Doppel gebunden.
 */
class ScheduledScanCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        // Keine echten ausgehenden Aufrufe/Jobs aus einem Scan-Lauf.
        Http::preventStrayRequests();
        Http::fake();
        Queue::fake();
    }

    // ── claims:escalate ──────────────────────────────────────────────────

    public function test_claim_escalation_reports_zero_without_overdue_cases(): void {
        $this->artisan('claims:escalate')
            ->expectsOutputToContain('Eskalationen versendet: 0')
            ->assertExitCode(0);
    }

    // ── attendance:close-open ────────────────────────────────────────────

    public function test_attendance_close_open_reports_zero_without_stale_sessions(): void {
        $this->artisan('attendance:close-open')
            ->expectsOutputToContain('Auto-closed 0 stale attendance(s).')
            ->assertExitCode(0);
    }

    // ── recurrence:generate ──────────────────────────────────────────────

    public function test_recurrence_generate_uses_the_default_lookahead(): void {
        $this->artisan('recurrence:generate')
            ->expectsOutputToContain('Lookahead: 28 Tage')
            ->assertExitCode(0);
    }

    public function test_recurrence_generate_clamps_a_nonsensical_lookahead(): void {
        // 0 oder negativ würde ein leeres Fenster ergeben — der Befehl hebt
        // auf mindestens einen Tag an, statt still nichts zu tun.
        $this->artisan('recurrence:generate', ['--lookahead' => '0'])
            ->expectsOutputToContain('Lookahead: 1 Tage')
            ->assertExitCode(0);
    }

    // ── print:purge-files ────────────────────────────────────────────────

    public function test_print_purge_files_reports_zero_without_expired_orders(): void {
        $this->artisan('print:purge-files')
            ->expectsOutputToContain('Bereinigt: 0 Druckaufträge')
            ->assertExitCode(0);
    }

    // ── privacy:retention-scan ───────────────────────────────────────────

    public function test_retention_scan_deletes_only_stale_help_counters(): void {
        HelpView::create([
            'organization_id' => $this->organization->id,
            'topic' => 'alt',
            'locale' => 'de',
            'created_at' => now()->subDays(120),
        ]);
        HelpView::create([
            'organization_id' => $this->organization->id,
            'topic' => 'frisch',
            'locale' => 'de',
            'created_at' => now()->subDays(3),
        ]);

        $this->artisan('privacy:retention-scan')
            ->expectsOutputToContain('1 Hilfe-Zähler (>90 Tage) entfernt')
            ->assertExitCode(0);

        $this->assertSame(['frisch'], HelpView::query()->pluck('topic')->all());
    }

    // ── privacy:deadlines ────────────────────────────────────────────────

    public function test_privacy_deadlines_runs_without_open_requests(): void {
        $this->artisan('privacy:deadlines')
            ->expectsOutputToContain('Compliance-Scan für 0 Organisation(en)')
            ->assertExitCode(0);
    }

    // ── events:* ─────────────────────────────────────────────────────────

    public function test_event_reminders_report_zero_without_due_events(): void {
        $this->artisan('events:dispatch-reminders')
            ->expectsOutputToContain('Dispatched 0 reminder(s).')
            ->assertExitCode(0);
    }

    public function test_event_recurrences_report_zero_without_series(): void {
        $this->artisan('events:materialize-recurrences')
            ->expectsOutputToContain('Materialized 0 occurrence(s).')
            ->assertExitCode(0);
    }

    public function test_certificate_expiry_reports_zero_without_participants(): void {
        $this->artisan('events:check-certificates')
            ->expectsOutputToContain('Notified 0 participant(s).')
            ->assertExitCode(0);
    }

    // ── inventory:expiring-lots ──────────────────────────────────────────

    public function test_expiring_lots_reports_zero_without_lots(): void {
        $this->artisan('inventory:expiring-lots')
            ->expectsOutputToContain('0 MHD-Meldung(en) versendet.')
            ->assertExitCode(0);
    }

    public function test_expiring_lots_accepts_a_single_organization(): void {
        $this->artisan('inventory:expiring-lots', [
            '--organization' => (string) $this->organization->id,
            '--days' => '0',
        ])->expectsOutputToContain('MHD-Meldung(en) versendet.')->assertExitCode(0);
    }

    // ── accounts:post ────────────────────────────────────────────────────

    public function test_time_account_posting_reports_an_empty_run(): void {
        $this->artisan('accounts:post', ['--days' => '5'])
            ->expectsOutputToContain('Zeitkonten-Lauf: 0 Buchungen')
            ->assertExitCode(0);
    }

    // ── accounting:check-filings ─────────────────────────────────────────

    public function test_filing_check_skips_organizations_without_local_accounting(): void {
        // Ohne lokale Buchhaltungshoheit ist der Lauf bewusst wirkungslos —
        // gemeldet wird bei ELSTER bzw. über die Steuerberatung.
        $this->artisan('accounting:check-filings', ['--date' => '2026-03-01'])
            ->expectsOutputToContain('0 Pflichten angelegt, 0 Erinnerungen versendet.')
            ->assertExitCode(0);
    }

    // ── news-feed:refresh ────────────────────────────────────────────────

    public function test_news_feed_refresh_is_a_noop_when_disabled(): void {
        config(['ui.news_feed.enabled' => false, 'ui.news_feed.url' => '']);

        $this->artisan('news-feed:refresh')
            ->expectsOutputToContain('deaktiviert oder nicht konfiguriert')
            ->assertExitCode(0);
    }

    public function test_news_feed_refresh_refuses_a_non_routable_url(): void {
        config(['ui.news_feed.enabled' => true, 'ui.news_feed.url' => 'http://127.0.0.1/feed.xml']);

        // SSRF-Schutz: interne Adressen sind keine Feed-Quellen.
        $this->artisan('news-feed:refresh')
            ->expectsOutputToContain('konnte nicht aktualisiert werden')
            ->assertExitCode(1);
    }

    // ── security:advisories-pull ─────────────────────────────────────────

    public function test_advisory_pull_reports_the_service_result(): void {
        $service = Mockery::mock(OsvAdvisoryService::class);
        $service->shouldReceive('pull')->once()->andReturn([
            'checked' => 12, 'open' => 2, 'new' => 1, 'resolved' => 3,
        ]);
        $this->app->instance(OsvAdvisoryService::class, $service);

        $this->artisan('security:advisories-pull')
            ->expectsOutputToContain('12 Pakete geprüft — 2 offene Advisories (1 neu, 3 als behoben markiert).')
            ->assertExitCode(0);
    }

    public function test_advisory_pull_fails_loudly_when_osv_is_unreachable(): void {
        $service = Mockery::mock(OsvAdvisoryService::class);
        $service->shouldReceive('pull')->once()->andThrow(new RuntimeException('OSV querybatch fehlgeschlagen (HTTP 503).'));
        $this->app->instance(OsvAdvisoryService::class, $service);

        $this->artisan('security:advisories-pull')
            ->expectsOutputToContain('Advisory-Pull fehlgeschlagen: OSV querybatch fehlgeschlagen (HTTP 503).')
            ->assertExitCode(1);
    }

    // ── domain:sync / domain:events ──────────────────────────────────────

    public function test_domain_sync_reports_zero_without_active_connections(): void {
        $this->artisan('domain:sync')
            ->expectsOutputToContain('0 Verbindung(en) abgeglichen.')
            ->assertExitCode(0);
    }

    public function test_domain_sync_can_be_limited_to_one_connection(): void {
        DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);

        $this->artisan('domain:sync', ['--connection' => '999999'])
            ->expectsOutputToContain('0 Verbindung(en) abgeglichen.')
            ->assertExitCode(0);
    }

    public function test_domain_event_polling_reports_zero_without_connections(): void {
        $this->artisan('domain:events', ['--limit' => '10'])
            ->expectsOutputToContain('0 neue Ereignisse gespeichert, 0 quittiert.')
            ->assertExitCode(0);
    }

    // ── cloud-intake:sync ────────────────────────────────────────────────

    public function test_cloud_intake_sync_succeeds_without_connections(): void {
        $this->artisan('cloud-intake:sync')->assertExitCode(0);
    }

    public function test_cloud_intake_sync_skips_connections_that_cannot_run(): void {
        // Ohne aktive Route ist die Verbindung nicht lauffähig — der Lauf darf
        // sie still überspringen statt einen Fehlschlag zu melden.
        $connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->artisan('cloud-intake:sync', ['--connection' => (string) $connection->id])
            ->assertExitCode(0);
    }
}
