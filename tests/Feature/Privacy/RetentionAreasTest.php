<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionAreasTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\{AuditLog, Customer, DiaryEntry, Organization, User};
use App\Models\Privacy\RetentionProposal;
use App\Services\Privacy\Retention\{RetentionRegistry, RetentionScanService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Löschkonzept Personendaten (Feature 130, MVP-694 — Vollscan H21): die
 * fünf neuen Katalog-Bereiche tragen Fristen je Rechtsraum; employee_records
 * (Anonymisierung) und customer_master (Review-Ausweis) erzeugen Vorschläge
 * im bestehenden zweistufigen Review-Flow; location_points/time_records/
 * documents_general sind bewusst policy-frei (Ausweis — Vollzug beim
 * location:purge-points-Job bzw. MAX-Frist über exports/gobd).
 */
final class RetentionAreasTest extends TestCase {
    use RefreshDatabase;

    private function makeOrg(string $region = 'DE'): Organization {
        $org = Organization::factory()->create(['legal_region' => $region]);
        app()->instance('currentOrganization', $org);

        return $org;
    }

    public function test_new_areas_carry_regional_periods_and_bases(): void {
        $registry = app(RetentionRegistry::class);
        $expectedYears = [
            // Bereich => [DE, AT, CH]
            'employee_records' => [3, 3, 5],
            'customer_master' => [3, 3, 5],
            'time_records' => [2, 2, 5],
            'documents_general' => [3, 3, 5],
        ];

        foreach ($expectedYears as $area => [$de, $at, $ch]) {
            $this->assertNotSame('', (string) config("retention.areas.{$area}.label"), "Label fehlt: {$area}");
            $this->assertSame($de, $registry->yearsFor($this->makeOrg('DE'), $area), "DE-Frist falsch: {$area}");
            $this->assertSame($at, $registry->yearsFor($this->makeOrg('AT'), $area), "AT-Frist falsch: {$area}");
            $this->assertSame($ch, $registry->yearsFor($this->makeOrg('CH'), $area), "CH-Frist falsch: {$area}");
            $this->assertNotNull($registry->basisFor($this->makeOrg('DE'), $area), "Rechtsgrundlage fehlt: {$area}");
        }

        // ArbZG-Hinweis: lohn-/steuerrelevante Zeiten bleiben über die
        // 10-Jahres-Bereiche gedeckt — das MAX-Fristende steht in der Basis.
        $this->assertStringContainsString('10 J.', (string) $registry->basisFor($this->makeOrg('DE'), 'time_records'));
    }

    public function test_location_points_area_is_wired_to_the_purge_job_setting(): void {
        $org = $this->makeOrg();
        $registry = app(RetentionRegistry::class);

        // Frist kommt aus location.retention_days (keine zweite Quelle) …
        $this->assertSame((int) config('location.retention_days'), $registry->daysFor('location_points'));
        $this->assertNull($registry->yearsFor($org, 'location_points'));
        $this->assertNotNull($registry->cutoffFor($org, 'location_points'));

        // … und der Vollzug bleibt beim Scheduler-Job location:purge-points:
        // BEWUSST keine Scan-Policy (keine Doppel-Löschung, keine
        // Punkt-für-Punkt-Vorschläge). Ebenso policy-frei: die reinen
        // Ausweis-Bereiche time_records und documents_general.
        $this->assertNull($registry->policy('location_points'));
        $this->assertNull($registry->policy('time_records'));
        $this->assertNull($registry->policy('documents_general'));
        // Job-Schlüssel enthält einen Punkt — kein dot-notation-Zugriff möglich.
        $jobs = (array) config('scheduler.jobs');
        $this->assertSame('location:purge-points', $jobs['location.purge_points']['command'] ?? null);

        // 0/negativ = unbegrenzt: dann darf auch der Ausweis keine Frist zeigen.
        config(['location.retention_days' => 0]);
        $this->assertNull($registry->daysFor('location_points'));
        $this->assertNull($registry->cutoffFor($org, 'location_points'));
    }

    public function test_employee_records_scan_proposes_candidates_without_anonymizing(): void {
        $org = $this->makeOrg();

        $candidate = User::factory()->user()->create([
            'organization_id' => $org->id,
            'deactivated_at' => now()->subYears(4),
            'left_at' => now()->subYears(4)->toDateString(),
        ]);
        // Aktiv (nicht deaktiviert) → kein Kandidat, trotz altem left_at.
        $active = User::factory()->user()->create([
            'organization_id' => $org->id,
            'left_at' => now()->subYears(4)->toDateString(),
        ]);
        // Frist läuft noch → kein Kandidat.
        $recent = User::factory()->user()->create([
            'organization_id' => $org->id,
            'deactivated_at' => now()->subYear(),
            'left_at' => now()->subYear()->toDateString(),
        ]);
        // Plattform-Betreiber sind ausgenommen (Sperr-Kriterium).
        $platform = User::factory()->user()->create([
            'organization_id' => $org->id,
            'is_platform_admin' => true,
            'deactivated_at' => now()->subYears(4),
            'left_at' => now()->subYears(4)->toDateString(),
        ]);

        app(RetentionScanService::class)->scan($org);

        $proposals = RetentionProposal::query()->where('area', 'employee_records')->get();
        $this->assertSame([$candidate->id], $proposals->map(fn($p) => (int) $p->subject_id)->all());
        $this->assertSame(RetentionProposal::STATUS_PENDING, $proposals->first()?->status);

        // Der Scan schlägt NUR vor — anonymisiert wird erst nach Bestätigung.
        $this->assertNull($candidate->fresh()?->anonymized_at);
        $this->assertNull($active->fresh()?->anonymized_at);
        $this->assertNull($recent->fresh()?->anonymized_at);
        $this->assertNull($platform->fresh()?->anonymized_at);
    }

    public function test_employee_records_purge_runs_the_anonymization(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $candidate = User::factory()->user()->create([
            'organization_id' => $org->id,
            'deactivated_at' => now()->subYears(4),
            'left_at' => now()->subYears(4)->toDateString(),
        ]);

        $service = app(RetentionScanService::class);
        $service->scan($org);
        $proposal = RetentionProposal::query()->where('area', 'employee_records')->firstOrFail();

        $service->approve($proposal, $admin);
        $this->assertNull($candidate->fresh()?->anonymized_at, 'approve anonymisiert noch nicht.');

        $service->purge($proposal->fresh(), $admin);

        $candidate->refresh();
        $this->assertNotNull($candidate->anonymized_at);
        $this->assertSame("Ausgeschiedene:r Mitarbeiter:in #{$candidate->id}", $candidate->name);
        $this->assertNotNull(User::query()->find($candidate->id), 'Anonymisierung löscht NICHT.');
        $this->assertSame(1, AuditLog::query()->where('event', 'user.anonymized')->count());
    }

    public function test_customer_master_scan_lists_only_dormant_customers_without_records(): void {
        $org = $this->makeOrg();
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $old = now()->subYears(4);

        $dormant = Customer::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $withTimes = Customer::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $withPortal = Customer::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $fresh = Customer::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        DiaryEntry::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'customer_id' => $withTimes->id,
        ]);
        // Portal-Konto (users.customer_id) = verknüpfte Strukturdaten → Ausnahme.
        User::factory()->user()->create(['organization_id' => $org->id, 'customer_id' => $withPortal->id]);

        Customer::query()->withoutGlobalScopes()
            ->whereIn('id', [$dormant->id, $withTimes->id, $withPortal->id])
            ->update(['updated_at' => $old]);

        $result = app(RetentionScanService::class)->scan($org);

        $proposals = RetentionProposal::query()->where('area', 'customer_master')->get();
        $this->assertSame([$dormant->id], $proposals->map(fn($p) => (int) $p->subject_id)->all());
        $this->assertSame(1, $result['exempt'], 'Portal-Kunde muss als Ausnahme zählen.');
        $this->assertNotNull($dormant->fresh(), 'Nur Review-Ausweis — der Scan löscht nichts.');
        $this->assertNotNull($fresh->fresh());

        // Bestätigter Purge löscht den Kunden UND sein leeres Auto-
        // Standardprojekt (CustomerObserver) — keine verwaiste Hülle.
        $defaultProjectId = (int) DB::table('projects')->where('customer_id', $dormant->id)->where('is_default', true)->value('id');
        $this->assertGreaterThan(0, $defaultProjectId);

        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $service = app(RetentionScanService::class);
        $proposal = $proposals->firstOrFail();
        $service->approve($proposal, $admin);
        $service->purge($proposal->fresh(), $admin);

        $this->assertNull(Customer::query()->withoutGlobalScopes()->find($dormant->id));
        $this->assertNull(DB::table('projects')->where('id', $defaultProjectId)->first());
    }
}
