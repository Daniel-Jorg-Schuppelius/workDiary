<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportedDataVisibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Overview;

use App\Enums\Integration\MarketplaceInboxStatus;
use App\Enums\Task\TaskStatus;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Project\{Project, Task};
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Models\BillbeeOrder;
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Models\EtsyReceipt;
use App\Plugins\Github\GithubPlugin;
use App\Plugins\PluginManager;
use App\Services\Search\GlobalSearchService;
use App\Services\WorkQueue\WorkQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Fremddaten in Suche, Arbeitsvorrat und Herkunftskennzeichen (Feature 181, MVP-1368/1369). */
class ImportedDataVisibilityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array<string, array{titles: list<string>, items: list<array<string, mixed>>}> */
    private function searchGroups(User $user, string $term): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
        app(PluginManager::class)->flushRuntimeCaches();
        $this->actingAs($user);

        return collect(app(GlobalSearchService::class)->groups($user, $term, [], 10, withActivities: false))
            ->mapWithKeys(fn (array $g) => [$g['key'] => ['titles' => array_column($g['items'], 'title'), 'items' => $g['items']]])
            ->all();
    }

    private function enable(string $pluginId, ?Organization $organization = null): void {
        PluginSetting::create(['organization_id' => ($organization ?? $this->organization)->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => []]);
    }

    private function receipt(int $receiptId, string $buyer, ?Organization $organization = null): EtsyReceipt {
        return EtsyReceipt::create([
            'organization_id' => ($organization ?? $this->organization)->id, 'receipt_id' => $receiptId, 'status' => 'paid',
            'was_paid' => true, 'was_shipped' => false, 'currency' => 'EUR', 'total_gross' => '49.90',
            'buyer' => ['name' => $buyer, 'email' => 'kauf@example.test'], 'ordered_at' => now(), 'inbox_status' => MarketplaceInboxStatus::Open,
        ]);
    }

    private function githubTask(string $title, string $url): Task {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'title' => $title, 'status' => TaskStatus::Open]);
        ExternalReference::link($this->organization, GithubPlugin::ID, GithubPlugin::EXT_TYPE_ISSUE, $task, '12', ['html_url' => $url]);

        return $task;
    }

    public function test_etsy_orders_are_searchable_for_admins_of_the_own_organization(): void {
        $this->enable(EtsyPlugin::ID);
        $this->receipt(900, 'Erika Zephyr');
        $foreign = Organization::factory()->create();
        $this->enable(EtsyPlugin::ID, $foreign);
        $this->receipt(901, 'Zephyr Fremd', $foreign);

        $groups = $this->searchGroups($this->admin, 'zephyr');

        $this->assertSame(['#900'], $groups['etsy_orders']['titles'] ?? null);
        $this->assertSame(route('admin.etsy.index', ['q' => '900']), $groups['etsy_orders']['items'][0]['url']);

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->assertArrayNotHasKey('etsy_orders', $this->searchGroups($member, 'zephyr'));
    }

    public function test_etsy_orders_stay_out_while_the_plugin_is_inactive(): void {
        $this->receipt(900, 'Erika Zephyr');

        $this->assertArrayNotHasKey('etsy_orders', $this->searchGroups($this->admin, 'zephyr'));
    }

    public function test_search_hit_opens_the_filtered_etsy_list(): void {
        $this->enable(EtsyPlugin::ID);
        $this->receipt(900, 'Erika Zephyr');
        $this->receipt(902, 'Otto Normal');

        $this->actingAs($this->admin)->get(route('admin.etsy.index', ['q' => '900']))
            ->assertOk()->assertSee('Erika Zephyr')->assertDontSee('Otto Normal');
    }

    public function test_billbee_orders_are_searchable_and_the_list_filters_by_number(): void {
        $this->enable(BillbeePlugin::ID);
        foreach (['BB-ZEPHYR' => 'Erika Zephyr', 'BB-OTHER' => 'Otto Normal'] as $number => $buyer) {
            BillbeeOrder::create([
                'organization_id' => $this->organization->id, 'billbee_order_id' => $number . '-id', 'order_number' => $number,
                'channel' => 'amazon', 'state' => 1, 'currency' => 'EUR', 'total_gross' => '10.00',
                'buyer' => ['FullName' => $buyer], 'ordered_at' => now(), 'inbox_status' => MarketplaceInboxStatus::Open,
            ]);
        }

        $groups = $this->searchGroups($this->admin, 'zephyr');

        $this->assertSame(['#BB-ZEPHYR'], $groups['billbee_orders']['titles'] ?? null);
        $this->actingAs($this->admin)->get($groups['billbee_orders']['items'][0]['url'])
            ->assertOk()->assertSee('Erika Zephyr')->assertDontSee('Otto Normal');
    }

    public function test_imported_task_names_its_plugin_in_search_and_work_queue(): void {
        $task = $this->githubTask('Zephyr Fehler beheben', 'https://github.com/acme/support/issues/12');
        $task->syncAssignees([$this->admin->id]);

        $groups = $this->searchGroups($this->admin, 'zephyr');
        $this->assertStringContainsString('GitHub Issues', (string) $groups['tasks']['items'][0]['subtitle']);

        $queue = app(WorkQueue::class);
        $items = $queue->itemsFor($this->admin, $this->organization, array_intersect_key($queue->sourcesFor($this->admin), ['task' => true]));
        $this->assertStringContainsString('GitHub Issues', (string) $items[0]->detail);
    }

    public function test_origin_badge_links_only_http_addresses(): void {
        $linked = $this->githubTask('Mit Link', 'https://github.com/acme/support/issues/12');
        $html = Blade::render('<x-origin-badge :subject="$task" />', ['task' => $linked]);
        $this->assertStringContainsString('href="https://github.com/acme/support/issues/12"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);

        $hostile = $this->githubTask('Mit Skript', 'javascript:alert(1)');
        $html = Blade::render('<x-origin-badge :subject="$task" />', ['task' => $hostile]);
        $this->assertStringContainsString('GitHub Issues', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('href=', $html);

        $plain = Task::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertSame('', trim(Blade::render('<x-origin-badge :subject="$task" />', ['task' => $plain])));
    }

    public function test_accounting_vouchers_of_any_program_are_searchable_and_the_hit_opens_the_feed(): void {
        AccountingVoucher::factory()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => 'sevdesk', 'voucher_number' => 'SD-ZEPHYR',
            'voucher_date' => '2025-03-14',
        ]);

        $groups = $this->searchGroups($this->admin, 'zephyr');

        $this->assertSame(['SD-ZEPHYR'], $groups['accounting_vouchers']['titles'] ?? null);
        $hit = $groups['accounting_vouchers']['items'][0];
        $this->assertStringContainsString('sevDesk', (string) $hit['subtitle']);
        // Der Kopfzeitraum steht auf dem laufenden Monat; das Belegdatum im Link hat Vorrang.
        $this->actingAs($this->admin)->get($hit['url'])->assertOk()
            ->assertViewHas('rows', fn ($rows): bool => collect($rows->items())->pluck('number')->contains('SD-ZEPHYR'));
    }
}
