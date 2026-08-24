<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryLogPruningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Integration;

use App\Enums\Integration\IntegrationOutboxStatus;
use App\Models\{IntegrationOutboxEntry, TodoistWebhookDelivery};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, J9: Zustellprotokolle und Outbox-Tabellen wuchsen
 * unbegrenzt. `model:prune` räumt erfolgreiche/bestätigte Einträge nach der
 * Aufbewahrungsfrist; offene Arbeit (pending) bleibt unangetastet.
 */
class DeliveryLogPruningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['integration.delivery_retention_days' => 90, 'integration.failed_retention_days' => 180]);
    }

    public function test_old_webhook_deliveries_are_pruned_but_recent_ones_stay(): void {
        $old = TodoistWebhookDelivery::query()->create(['delivery_id' => 'old', 'organization_id' => $this->organization->id, 'received_at' => now()->subDays(120)]);
        $recent = TodoistWebhookDelivery::query()->create(['delivery_id' => 'recent', 'organization_id' => $this->organization->id, 'received_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [TodoistWebhookDelivery::class]])->assertSuccessful();

        $this->assertDatabaseMissing('todoist_webhook_deliveries', ['id' => $old->id]);
        $this->assertDatabaseHas('todoist_webhook_deliveries', ['id' => $recent->id]);
    }

    public function test_outbox_keeps_pending_work_and_prunes_confirmed_entries(): void {
        $make = fn (string $key, IntegrationOutboxStatus $status, int $ageDays): IntegrationOutboxEntry => tap(IntegrationOutboxEntry::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl',
            'operation' => 'time_entry.create',
            'payload' => ['time_entry_id' => 1],
            'idempotency_key' => $key,
            'status' => $status->value,
            'attempts' => 1,
        ]), fn (IntegrationOutboxEntry $entry) => $entry->forceFill(['updated_at' => now()->subDays($ageDays)])->saveQuietly());

        $confirmedOld = $make('confirmed-old', IntegrationOutboxStatus::Confirmed, 120);
        $confirmedRecent = $make('confirmed-recent', IntegrationOutboxStatus::Confirmed, 5);
        $pendingOld = $make('pending-old', IntegrationOutboxStatus::Pending, 400);
        $failedOld = $make('failed-old', IntegrationOutboxStatus::Failed, 200);
        $failedRecent = $make('failed-recent', IntegrationOutboxStatus::Failed, 100);

        $this->artisan('model:prune', ['--model' => [IntegrationOutboxEntry::class]])->assertSuccessful();

        $this->assertDatabaseMissing('integration_outbox', ['id' => $confirmedOld->id]);
        $this->assertDatabaseMissing('integration_outbox', ['id' => $failedOld->id]);
        $this->assertDatabaseHas('integration_outbox', ['id' => $confirmedRecent->id]);
        $this->assertDatabaseHas('integration_outbox', ['id' => $pendingOld->id]);
        $this->assertDatabaseHas('integration_outbox', ['id' => $failedRecent->id]);
    }
}
