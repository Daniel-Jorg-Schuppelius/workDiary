<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\{Customer, ForeignCustomer, IntegrationInboxItem, MonthClosure, TimeEntry, User};
use App\Plugins\Fritzbox\{FritzboxImportService, FritzboxPlugin};
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FritzboxImportTest extends TestCase {
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

    /** @return array<string, mixed> */
    private function config(array $overrides = []): array {
        return $overrides + [
            'default_billable' => true,
            'default_user_id' => null,
            'min_call_minutes' => 2,
            'call_lead_minutes' => 15,
            'own_number_allowlist' => [],
            'type3_outgoing' => false,
        ];
    }

    private function makeCall(array $overrides = []): FritzboxCall {
        $startedAt = $overrides['startedAt'] ?? CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC');
        $minutes = $overrides['durationMinutes'] ?? 10;

        return new FritzboxCall(
            type: $overrides['type'] ?? FritzboxCall::TYPE_INCOMING,
            direction: $overrides['direction'] ?? FritzboxCall::DIR_IN,
            startedAt: $startedAt,
            endedAt: $overrides['endedAt'] ?? $startedAt->addMinutes($minutes),
            durationMinutes: $minutes,
            numberRaw: $overrides['numberRaw'] ?? '02219567000',
            e164: array_key_exists('e164', $overrides) ? $overrides['e164'] : '+492219567000',
            name: $overrides['name'] ?? null,
            ownLine: $overrides['ownLine'] ?? '97911585',
        );
    }

    public function test_known_customer_number_creates_time_entry(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0221 9567000',
        ]);

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);

        $this->assertSame('created', $status);

        $entry = TimeEntry::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($customer->defaultProjectOrCreate()->id, $entry->project_id);
        $this->assertSame(10, $entry->minutes);
        $this->assertTrue((bool) $entry->billable);
        $this->assertStringContainsString('Telefonat', (string) $entry->description);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => FritzboxPlugin::ID,
            'external_type' => 'call',
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_foreign_customer_number_wins_and_books_its_project(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Endkunde Nord',
            'phone' => '+49 221 9567000',
        ]);

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);

        $this->assertSame('created', $status);
        $entry = TimeEntry::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($foreign->defaultProjectOrCreate()->id, $entry->project_id);
    }

    public function test_unknown_number_goes_to_inbox_grouped_by_number(): void {
        $first = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);
        $second = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall([
            'startedAt' => CarbonImmutable::parse('2026-07-21 14:00:00', 'UTC'),
        ]), $this->owner->id);

        $this->assertSame(['pending', 'pending'], [$first, $second]);
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());

        $items = IntegrationInboxItem::query()
            ->where('plugin_id', FritzboxPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->get();
        $this->assertCount(2, $items);
        $this->assertSame(['+492219567000'], $items->pluck('group_key')->unique()->values()->all());
        $this->assertSame(IntegrationInboxItem::CASE_UNMATCHED, $items->first()->case_type);
    }

    public function test_short_missed_and_suppressed_calls_are_filtered(): void {
        $short = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(['durationMinutes' => 1]), $this->owner->id);
        $missed = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall([
            'type' => FritzboxCall::TYPE_MISSED,
            'durationMinutes' => 0,
        ]), $this->owner->id);
        $suppressed = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall([
            'e164' => null,
            'numberRaw' => '',
        ]), $this->owner->id);

        $this->assertSame('skipped', $short);
        $this->assertSame('ignored', $missed);
        $this->assertSame('ignored', $suppressed);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_own_number_allowlist_filters_other_lines(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '02219567000',
        ]);
        $config = $this->config(['own_number_allowlist' => ['97911585']]);

        $private = $this->service()->bookCall($this->organization, $config, $this->makeCall(['ownLine' => '921014723']), $this->owner->id);
        $business = $this->service()->bookCall($this->organization, $config, $this->makeCall(), $this->owner->id);

        $this->assertSame('ignored', $private);
        $this->assertSame('created', $business);
    }

    public function test_reimport_is_idempotent(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '02219567000',
        ]);

        $first = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);
        $second = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);

        $this->assertSame('created', $first);
        $this->assertSame('skipped', $second);
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_learned_number_matches_before_master_data(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->service()->rememberNumber($this->organization, '+492219567000', $customer);

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);

        $this->assertSame('created', $status);
        $entry = TimeEntry::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($customer->defaultProjectOrCreate()->id, $entry->project_id);
    }

    public function test_second_learned_number_for_same_customer_lands_as_alias(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->service()->rememberNumber($this->organization, '+492219567000', $customer);
        $this->service()->rememberNumber($this->organization, '+492219567999', $customer);

        $this->assertDatabaseHas('external_reference_aliases', [
            'plugin_id' => FritzboxPlugin::ID,
            'external_type' => 'number',
            'external_id' => '+492219567999',
        ]);

        // Beide Nummern lösen auf denselben Kunden auf.
        $this->assertTrue($this->service()->matchTarget($this->organization, '+492219567999')?->is($customer));
    }

    public function test_locked_month_blocks_creation(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '02219567000',
        ]);
        MonthClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'period_year' => 2026,
            'period_month' => 7,
            'status' => MonthClosureStatus::Locked,
            'days_total' => 31,
            'days_with_attendance' => 0,
            'days_closed' => 0,
        ]);

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall(), $this->owner->id);

        $this->assertSame('locked', $status);
        $this->assertSame(0, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_import_from_csv_end_to_end(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0170 9024670',
        ]);

        $csv = implode("\r\n", [
            'sep=;',
            'Typ;Datum;Name;Rufnummer;Landes-/Ortsnetzbereich;Nebenstelle;Eigene Rufnummer;Dauer',
            '1;20.07.26 10:45;Andreas Fichter;01709024670;;ISDN Gerät;97911585;0:10', // bekannt → created
            '2;20.07.26 14:37;;024339392801;Hückelhoven;;97911585;0:00',              // verpasst → ignored
            '1;20.07.26 15:00;;015734335787;;Firma;97911585;0:01',                    // < 2 min → skipped
            '4;21.07.26 09:08;;030208477964;Berlin;ISDN Gerät;97911585;0:04',         // unbekannt → pending
        ]);

        $result = $this->service()->importFromCsv($this->organization, $csv, $this->config());

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['ignored']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['pending']);
        $this->assertSame(0, $result['linked']);

        // Reimport derselben Liste doppelt nichts.
        $again = $this->service()->importFromCsv($this->organization, $csv, $this->config());
        $this->assertSame(0, $again['created']);
        $this->assertSame(2, $again['skipped']); // bekannter Anruf + <2-min-Anruf
        $this->assertSame(1, $again['pending']); // Inbox-Item bleibt idempotent offen
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, IntegrationInboxItem::query()->where('status', IntegrationInboxItem::STATUS_OPEN)->count());
    }
}
