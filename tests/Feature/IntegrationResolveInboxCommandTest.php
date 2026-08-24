<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationResolveInboxCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, ExternalReference, IntegrationInboxItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: Massen-Auflösung der Zuordnungs-Inbox über
 * integration:resolve-inbox — eindeutige Exact-Treffer via --auto-link,
 * Neuanlage via --create, Dry-Run schreibt nichts.
 */
class IntegrationResolveInboxCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function item(array $overrides = []): IntegrationInboxItem {
        return IntegrationInboxItem::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl',
            'source' => 'api',
            'target_type' => (new Customer)->getMorphClass(),
            'external_type' => 'client',
            'external_id' => 'tg-1',
            'dedupe_key' => 'client:tg-1',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => ['client' => 'Neu AG'],
            'mapped_snapshot' => ['name' => 'Neu AG', 'vat_id' => 'DE123'],
            'display_title' => 'Neu AG',
        ], $overrides));
    }

    private function resolveInbox(array $options = []): \Illuminate\Testing\PendingCommand {
        /** @var \Illuminate\Testing\PendingCommand */
        return $this->artisan('integration:resolve-inbox', array_merge([
            '--organization' => (string) $this->organization->id,
        ], $options));
    }

    public function test_auto_link_assigns_a_unique_exact_match(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Neu AG',
            'vat_id' => 'DE123',
        ]);
        $item = $this->item();

        $this->resolveInbox(['--auto-link' => true])
            ->expectsOutputToContain('zugeordnet: 1')
            ->assertExitCode(0);

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LINKED, $item->status);
        $this->assertDatabaseHas('external_references', [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl',
            'external_type' => 'client',
            'external_id' => 'tg-1',
            'referenceable_id' => $customer->id,
        ]);
    }

    public function test_create_builds_a_new_record_for_unmatched_items(): void {
        $item = $this->item();

        $this->resolveInbox(['--create' => true])
            ->expectsOutputToContain('angelegt: 1')
            ->assertExitCode(0);

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_CREATED, $item->status);
        $this->assertNotNull(Customer::query()
            ->where('organization_id', $this->organization->id)
            ->where('name', 'Neu AG')
            ->first());
    }

    public function test_without_flags_items_stay_open(): void {
        $item = $this->item();

        $this->resolveInbox()
            ->expectsOutputToContain('offen: 1')
            ->assertExitCode(0);

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_OPEN, $item->status);
        $this->assertSame(IntegrationInboxItem::CASE_UNMATCHED, $item->case_type);
    }

    public function test_dry_run_counts_but_writes_nothing(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Neu AG',
            'vat_id' => 'DE123',
        ]);
        $item = $this->item();

        $this->resolveInbox(['--auto-link' => true, '--dry-run' => true])
            ->expectsOutputToContain('zugeordnet: 1')
            ->assertExitCode(0);

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_OPEN, $item->status);
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', 'toggl')->count());
    }
}
