<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\{Customer, DiaryEntry, Expense, Organization, TravelLog, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CSV- und PDF-Exporte dürfen niemals Datensätze einer fremden Organisation
 * enthalten. Tests authentifizieren einen Admin aus Organisation A, erzeugen
 * Datensätze in Organisation B (Cross-Tenant-Setup) und prüfen, dass der
 * Export ausschließlich Daten der eigenen Organisation liefert.
 *
 * Referenz: ../WorkDiary-Architecture/security/tenant-audit-2026.md (Abschnitt „Exporte").
 */
class ExportTenantTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['slug' => 'exp-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'exp-b']);

        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->adminB = User::factory()->admin()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_diary_csv_export_does_not_leak_cross_org_entries(): void {
        $entryB = $this->withOrg($this->orgB, fn() => DiaryEntry::factory()->create([
            'user_id' => $this->adminB->id,
            'content' => 'GEHEIM-ORG-B-DIARY',
        ]));

        $response = $this->getAsAdminA('diary.export.csv', [
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->addMonth()->toDateString(),
        ]);

        $response->assertOk();
        $body = $this->streamContent($response);
        $this->assertStringNotContainsString('GEHEIM-ORG-B-DIARY', $body);
        $this->assertStringNotContainsString((string) $entryB->id, $this->csvIdColumn($body));
    }

    public function test_customer_csv_export_does_not_leak_cross_org_customers(): void {
        $customerB = $this->withOrg($this->orgB, fn() => Customer::factory()->create([
            'name' => 'GEHEIM-ORG-B-KUNDE',
        ]));

        $response = $this->getAsAdminA('customers.export');

        $response->assertOk();
        $body = $this->streamContent($response);
        $this->assertStringNotContainsString('GEHEIM-ORG-B-KUNDE', $body);
        $this->assertStringNotContainsString((string) $customerB->id, $body);
    }

    public function test_travel_log_csv_export_does_not_leak_cross_org_logs(): void {
        $this->withOrg($this->orgB, fn() => TravelLog::factory()->create([
            'user_id' => $this->adminB->id,
            'purpose' => 'GEHEIM-ORG-B-FAHRT',
            'date' => now()->toDateString(),
        ]));

        $response = $this->getAsAdminA('travel-logs.export', [
            'from' => now()->subWeek()->toDateString(),
            'to' => now()->addWeek()->toDateString(),
        ]);

        $response->assertOk();
        $body = $this->streamContent($response);
        $this->assertStringNotContainsString('GEHEIM-ORG-B-FAHRT', $body);
    }

    public function test_expense_csv_export_does_not_leak_cross_org_expenses(): void {
        $this->withOrg($this->orgB, fn() => Expense::factory()->create([
            'user_id' => $this->adminB->id,
            'vendor' => 'GEHEIM-ORG-B-HAENDLER',
            'date' => now()->toDateString(),
        ]));

        $response = $this->getAsAdminA('expenses.export', [
            'from' => now()->subWeek()->toDateString(),
            'to' => now()->addWeek()->toDateString(),
        ]);

        $response->assertOk();
        $body = $this->streamContent($response);
        $this->assertStringNotContainsString('GEHEIM-ORG-B-HAENDLER', $body);
    }

    /** Erfasst den Body eines StreamedResponse über Output-Buffering. */
    private function streamContent(TestResponse $response): string {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }

    /** Reduziert CSV auf die erste Spalte (ID), um Substring-Kollisionen zu vermeiden. */
    private function csvIdColumn(string $csv): string {
        $ids = [];
        foreach (preg_split('/\r?\n/', $csv) ?: [] as $line) {
            $cells = str_getcsv($line, ';');
            if ($cells === [null] || $cells === false || ! isset($cells[0])) {
                continue;
            }
            $ids[] = (string) $cells[0];
        }

        return implode("\n", $ids);
    }

    private function getAsAdminA(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->adminA)->get(route($routeName, $parameters));
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
