<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerformanceMeasurementCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Models\{AuditLog, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use Tests\TestCase;

/**
 * Die beiden Messwerkzeuge aus MVP-722 (Vollscan 2026-08-23, A5/A8/A13/A14):
 * `perf:seed-load` erzeugt den Listen-Lastdatensatz, `audit:measure-chain-contention`
 * misst die Sperrkonkurrenz der Audit-Kette. Beide sind Werkzeuge, kein
 * Bestand — die Tests sichern das Grundverhalten und die Produktivsperre.
 */
class PerformanceMeasurementCommandsTest extends TestCase {
    use RefreshDatabase;

    public function test_seed_load_writes_measurement_rows(): void {
        $exit = Artisan::call('perf:seed-load', [
            '--orgs' => 2,
            '--time-entries' => 10,
            '--audit-logs' => 10,
            '--diary-entries' => 8,
            '--travel-logs' => 6,
            '--invoices' => 4,
            '--quotes' => 4,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(10, DB::table('time_entries')->count());
        // Nur die Messzeilen zählen — Organisationen/Benutzer der Grundausstattung
        // schreiben selbst Audit-Einträge.
        $this->assertSame(10, DB::table('audit_logs')->where('auditable_type', 'App\\Models\\TimeEntry')->count());
        $this->assertSame(8, DB::table('diary_entries')->count());
        $this->assertSame(4, DB::table('quotes')->count());

        // Verteilt über beide Organisationen — sonst misst der Lauf nur eine.
        $this->assertSame(2, DB::table('time_entries')->distinct()->count('organization_id'));
        $this->assertStringContainsString('keine Hash-Kette', Artisan::output());
    }

    public function test_seed_load_measures_without_seeding(): void {
        Organization::factory()->count(2)->create();

        $exit = Artisan::call('perf:seed-load', [
            '--orgs' => 2,
            '--time-entries' => 0,
            '--audit-logs' => 0,
            '--diary-entries' => 0,
            '--travel-logs' => 0,
            '--invoices' => 0,
            '--quotes' => 0,
            '--explain' => true,
            '--only' => 'auditLogList,quotesByStatus',
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('auditLogList', $output);
        $this->assertStringContainsString('quotesByStatus', $output);
        $this->assertStringNotContainsString('documentFeed', $output, '--only muss die übrigen Fälle auslassen.');
    }

    public function test_chain_contention_worker_writes_and_reports_json(): void {
        $organization = Organization::factory()->create();
        User::factory()->create(['organization_id' => $organization->id]);

        $exit = Artisan::call('audit:measure-chain-contention', [
            '--worker' => true,
            '--organization' => $organization->id,
            '--inserts' => 3,
        ]);

        $this->assertSame(0, $exit);

        $payload = json_decode(trim(Artisan::output()), true);
        $this->assertIsArray($payload);
        $this->assertSame(3, $payload['rows']);
        $this->assertSame(0, $payload['deadlocks'], 'Ein einzelner Schreiber darf sich nicht selbst verklemmen.');

        $this->assertSame(3, AuditLog::query()->where('event', 'perf.measure')->count());

        // Die Zeilen landen in der Kette DIESER Organisation (MVP-722).
        $head = DB::table('audit_chain_heads')->where('chain', 'audit_logs:' . $organization->id)->first();
        $this->assertNotNull($head);
        $this->assertSame(
            AuditLog::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
            (int) $head->height,
            'Der Kettenkopf zählt genau die Zeilen dieser Organisation.',
        );
    }

    public function test_chain_contention_needs_two_organizations(): void {
        Organization::factory()->create();

        $this->assertSame(1, Artisan::call('audit:measure-chain-contention', ['--inserts' => 1]));
        $this->assertStringContainsString('Mindestens zwei Organisationen', Artisan::output());
    }
}
