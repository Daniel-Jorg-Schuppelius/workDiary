<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ForeignCustomer, IntegrationInboxItem, TimeEntry, User};
use App\Plugins\Fritzbox\{FritzboxGroupBooker, FritzboxImportService, FritzboxPlugin, FritzboxSuggestionService};
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use App\Services\Integration\InboxGroupBookerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FritzboxInboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
    }

    private function service(): FritzboxImportService {
        return new FritzboxImportService;
    }

    private function booker(): FritzboxGroupBooker {
        return new FritzboxGroupBooker($this->service(), new FritzboxSuggestionService);
    }

    /** @return array<string, mixed> */
    private function config(): array {
        return [
            'default_billable' => true,
            'default_user_id' => null,
            'min_call_minutes' => 2,
            'call_lead_minutes' => 15,
            'own_number_allowlist' => [],
            'type3_outgoing' => false,
        ];
    }

    private function makeCall(string $start, int $minutes = 10, ?string $name = null): FritzboxCall {
        $startedAt = CarbonImmutable::parse($start, 'UTC');

        return new FritzboxCall(
            type: FritzboxCall::TYPE_INCOMING,
            direction: FritzboxCall::DIR_IN,
            startedAt: $startedAt,
            endedAt: $startedAt->addMinutes($minutes),
            durationMinutes: $minutes,
            numberRaw: '02219567000',
            e164: '+492219567000',
            name: $name,
            ownLine: '97911585',
        );
    }

    private function stagePendingCalls(): void {
        $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 09:00:00'), $this->owner->id);
        $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-21 10:00:00'), $this->owner->id);
    }

    public function test_registry_resolves_fritzbox_booker(): void {
        $this->assertInstanceOf(FritzboxGroupBooker::class, (new InboxGroupBookerRegistry)->for(FritzboxPlugin::ID));
    }

    public function test_groups_expose_phone_number_form(): void {
        $this->stagePendingCalls();

        $groups = $this->booker()->groups($this->organization);

        $this->assertCount(1, $groups);
        $group = $groups->first();
        $this->assertSame('phone_number', $group['form']);
        $this->assertSame('+492219567000', $group['group_key']);
        $this->assertSame(2, $group['count']);
        $this->assertSame(20, $group['minutes']);
        $this->assertFalse($group['shared']);
        $this->assertCount(2, $group['entries']);
    }

    public function test_assign_with_remember_books_and_learns_number(): void {
        $this->stagePendingCalls();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $result = $this->booker()->book($this->organization, '+492219567000', [
            'action' => 'assign',
            'customer' => (string) $customer->sqid,
            'remember' => '1',
        ]);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, IntegrationInboxItem::query()->where('status', IntegrationInboxItem::STATUS_OPEN)->count());

        // Gelernt: der nächste Anruf dieser Nummer bucht automatisch.
        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-22 11:00:00'), $this->owner->id);
        $this->assertSame('created', $status);
    }

    public function test_assign_without_remember_learns_nothing(): void {
        $this->stagePendingCalls();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->booker()->book($this->organization, '+492219567000', [
            'action' => 'assign',
            'customer' => (string) $customer->sqid,
            'remember' => '0',
        ]);

        $this->assertNull($this->service()->matchTarget($this->organization, '+492219567000'));

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-22 11:00:00'), $this->owner->id);
        $this->assertSame('pending', $status);
    }

    public function test_assign_to_foreign_customer_wins_over_customer(): void {
        $this->stagePendingCalls();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Endkunde Süd',
        ]);

        $this->booker()->book($this->organization, '+492219567000', [
            'action' => 'assign',
            'customer' => (string) $customer->sqid,
            'foreign_customer' => (string) $foreign->sqid,
            'remember' => '1',
        ]);

        $entry = TimeEntry::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($foreign->defaultProjectOrCreate()->id, $entry->project_id);
        $this->assertTrue($this->service()->matchTarget($this->organization, '+492219567000')?->is($foreign));
    }

    public function test_shared_number_splits_into_single_groups_and_never_learns(): void {
        $this->stagePendingCalls();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $result = $this->booker()->book($this->organization, '+492219567000', ['action' => 'shared']);
        $this->assertSame(['created' => 0, 'skipped' => 0], $result);

        $groups = $this->booker()->groups($this->organization);
        $this->assertCount(2, $groups);
        $this->assertTrue($groups->every(fn (array $g): bool => $g['shared'] === true && $g['count'] === 1));

        // Folgeanrufe landen einzeln in der Inbox — trotz späterer Zuordnung.
        $single = $groups->first();
        $this->booker()->book($this->organization, (string) $single['group_key'], [
            'action' => 'assign',
            'customer' => (string) $customer->sqid,
            'remember' => '1', // wird bei geteilten Nummern bewusst ignoriert
        ]);
        $this->assertNull($this->service()->matchTarget($this->organization, '+492219567000'));

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-23 09:00:00'), $this->owner->id);
        $this->assertSame('pending', $status);
        $this->assertStringContainsString('|', (string) IntegrationInboxItem::query()
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->orderByDesc('id')
            ->firstOrFail()
            ->group_key);
    }

    public function test_ignore_dismisses_and_filters_future_calls(): void {
        $this->stagePendingCalls();

        $result = $this->booker()->book($this->organization, '+492219567000', ['action' => 'ignore']);

        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, IntegrationInboxItem::query()->where('status', IntegrationInboxItem::STATUS_OPEN)->count());

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-25 09:00:00'), $this->owner->id);
        $this->assertSame('ignored', $status);
    }

    public function test_dismiss_is_temporary_future_calls_reappear(): void {
        $this->stagePendingCalls();

        $count = $this->booker()->dismiss($this->organization, '+492219567000');
        $this->assertSame(2, $count);

        // Neuer Anruf derselben Nummer taucht wieder auf.
        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-08-03 09:00:00'), $this->owner->id);
        $this->assertSame('pending', $status);
    }

    public function test_suggestion_by_exact_name(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Fichter Elektro',
        ]);
        $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 09:00:00', 10, 'Fichter Elektro'), $this->owner->id);

        $group = $this->booker()->groups($this->organization)->firstOrFail();

        $this->assertSame((string) $customer->sqid, $group['suggested_customer_sqid']);
    }

    public function test_suggestion_by_overlap_with_existing_time(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $customer->defaultProjectOrCreate()->id,
            'user_id' => $this->owner->id,
            'started_at' => CarbonImmutable::parse('2026-07-20 08:30:00', 'UTC'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 09:30:00', 'UTC'),
        ]);

        // Anruf einer FREMDEN Nummer, der die erfasste Zeit überlappt.
        $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 09:00:00'), $this->owner->id);

        $group = $this->booker()->groups($this->organization)->firstOrFail();

        $this->assertSame((string) $customer->sqid, $group['suggested_customer_sqid']);
    }
}
