<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeProvider};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Importbericht Cloud-Dokumenteingang (Feature 080 P9; Audit 2026-08, W4.4):
 * Aggregation je Status/Anbieter, Zeitraumgrenze, Mandantengrenze,
 * Report-Berechtigung und CSV-Export.
 */
class CloudIntakeReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;
    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Google,
            'root_folder_path' => '/Rechnungen',
        ]);
    }

    public function test_report_aggregates_statuses_of_the_period(): void {
        $this->item(CloudIntakeItemStatus::Imported, '2026-06-10');
        $this->item(CloudIntakeItemStatus::Imported, '2026-06-12');
        $this->item(CloudIntakeItemStatus::Rejected, '2026-06-14', 'blocked_extension');
        // Außerhalb des Zeitraums — darf nicht mitzählen.
        $this->item(CloudIntakeItemStatus::Imported, '2026-05-30');

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.cloud-intake'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('total'));
        $this->assertSame(2, $response->viewData('byStatus')['imported']);
        $this->assertSame(1, $response->viewData('byStatus')['rejected']);
        $this->assertSame([['reason' => 'blocked_extension', 'count' => 1]], $response->viewData('byReason'));
    }

    public function test_connection_rows_list_connections_without_items(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.cloud-intake'));

        $response->assertOk();
        $rows = $response->viewData('connections');
        $this->assertCount(1, $rows);
        $this->assertSame('/Rechnungen', $rows[0]['label']);
        $this->assertSame(0, $rows[0]['imported']);
    }

    public function test_report_never_shows_items_of_another_organization(): void {
        $foreign = Organization::factory()->create();
        $foreignConnection = CloudDocumentConnection::factory()->create([
            'organization_id' => $foreign->id,
            'provider' => CloudIntakeProvider::Dropbox,
        ]);
        CloudDocumentItem::query()->create([
            'organization_id' => $foreign->id,
            'connection_id' => $foreignConnection->id,
            'provider' => CloudIntakeProvider::Dropbox,
            'external_item_id' => 'foreign-1',
            'revision' => 'rev-x',
            'source_path' => '/fremd.pdf',
            'status' => CloudIntakeItemStatus::Imported,
        ])->forceFill(['created_at' => CarbonImmutable::parse('2026-06-11')])->save();

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.cloud-intake'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('total'));
        $this->assertCount(1, $response->viewData('connections')); // nur die eigene
    }

    public function test_report_requires_report_permission(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.cloud-intake'))
            ->assertForbidden();
    }

    public function test_csv_export_contains_the_rows(): void {
        $this->item(CloudIntakeItemStatus::Rejected, '2026-06-14', 'blocked_extension', '/virus.exe');

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.cloud-intake', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('/virus.exe', (string) $response->getContent());
    }

    private function item(CloudIntakeItemStatus $status, string $date, ?string $reason = null, string $path = '/beleg.pdf'): void {
        static $counter = 0;
        $counter++;

        $item = CloudDocumentItem::query()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $this->connection->id,
            'provider' => CloudIntakeProvider::Google,
            'external_item_id' => 'ext-' . $counter,
            'revision' => 'rev-' . $counter,
            'source_path' => $path,
            'status' => $status,
            'status_reason' => $reason,
        ]);
        // created_at ist nicht fillable — der Zeitraumbezug wird nachgezogen.
        $item->forceFill(['created_at' => CarbonImmutable::parse($date)])->save();
    }
}
