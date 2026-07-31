<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxCorrelationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\{Customer, ExternalReferenceAlias, MonthClosure, Project, TimeEntry, User};
use App\Plugins\Fritzbox\{FritzboxImportService, FritzboxPlugin};
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Verschmelzung Telefonat ↔ bestehende (Fernwartungs-)Zeit: Anrufe, die einen
 * Eintrag desselben Kunden überlappen oder ihm ≤ Lead-Fenster vorausgehen,
 * werden verknüpft statt doppelt gebucht; der Start wandert auf den
 * Anrufbeginn. Exportierte Einträge und abgeschlossene Monate bleiben
 * unangetastet.
 */
class FritzboxCorrelationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '02219567000',
        ]);
        $this->project = $this->customer->defaultProjectOrCreate();
    }

    private function service(): FritzboxImportService {
        return new FritzboxImportService;
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

    private function makeCall(string $start, int $minutes): FritzboxCall {
        $startedAt = CarbonImmutable::parse($start, 'UTC');

        return new FritzboxCall(
            type: FritzboxCall::TYPE_INCOMING,
            direction: FritzboxCall::DIR_IN,
            startedAt: $startedAt,
            endedAt: $startedAt->addMinutes($minutes),
            durationMinutes: $minutes,
            numberRaw: '02219567000',
            e164: '+492219567000',
            name: null,
            ownLine: '97911585',
        );
    }

    private function existingEntry(string $start, string $end, ?Project $project = null): TimeEntry {
        return TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => ($project ?? $this->project)->id,
            'user_id' => $this->owner->id,
            'started_at' => CarbonImmutable::parse($start, 'UTC'),
            'ended_at' => CarbonImmutable::parse($end, 'UTC'),
            'description' => 'Anydesk — Server (Wartung)',
        ]);
    }

    public function test_overlapping_call_links_without_moving_start(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 09:10:00', 10), $this->owner->id);

        $this->assertSame('linked', $status);
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame('2026-07-20 09:00:00', $entry->fresh()->started_at->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => FritzboxPlugin::ID,
            'external_type' => 'call',
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_call_starting_before_entry_pulls_start_forward(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:50:00', 15), $this->owner->id);

        $this->assertSame('linked', $status);
        $fresh = $entry->fresh();
        $this->assertSame('2026-07-20 08:50:00', $fresh->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(70, $fresh->minutes); // 08:50–10:00, Hook rechnet neu
    }

    public function test_call_ending_within_lead_window_links_and_pulls_start(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        // Anruf 08:40–08:50, Sitzung ab 09:00 → Lücke 10 min ≤ 15 min Lead.
        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:40:00', 10), $this->owner->id);

        $this->assertSame('linked', $status);
        $this->assertSame('2026-07-20 08:40:00', $entry->fresh()->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_call_outside_lead_window_creates_own_entry(): void {
        $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        // Anruf 08:30–08:40, Lücke 20 min > 15 min Lead → eigene Buchung.
        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:30:00', 10), $this->owner->id);

        $this->assertSame('created', $status);
        $this->assertSame(2, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_gap_of_zero_counts_as_lead(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        // Anruf endet exakt um 09:00 (Minutengranularität der Box).
        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:55:00', 5), $this->owner->id);

        $this->assertSame('linked', $status);
        $this->assertSame('2026-07-20 08:55:00', $entry->fresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_second_call_lands_as_alias_and_start_is_monotonic(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        $first = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:50:00', 10), $this->owner->id);
        $second = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:42:00', 5), $this->owner->id);

        $this->assertSame(['linked', 'linked'], [$first, $second]);
        // Zweiter Anruf (früher) zieht weiter vor; Reihenfolge egal, Start = Minimum.
        $this->assertSame('2026-07-20 08:42:00', $entry->fresh()->started_at->format('Y-m-d H:i:s'));

        $this->assertSame(1, ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', FritzboxPlugin::ID)
            ->where('external_type', 'call')
            ->count());
    }

    public function test_exported_entry_is_linked_but_never_modified(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');
        $entry->forceFill(['exported' => true])->save();

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:50:00', 10), $this->owner->id);

        $this->assertSame('linked', $status);
        $this->assertSame('2026-07-20 09:00:00', $entry->fresh()->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count()); // trotzdem keine Doppelbuchung
    }

    public function test_locked_month_links_without_modification(): void {
        $entry = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00');
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

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 08:50:00', 10), $this->owner->id);

        $this->assertSame('linked', $status);
        $this->assertSame('2026-07-20 09:00:00', $entry->fresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_entry_of_other_customer_does_not_cover(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 10:00:00', $other->defaultProjectOrCreate());

        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 09:10:00', 10), $this->owner->id);

        $this->assertSame('created', $status);
        $this->assertSame(2, TimeEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_largest_overlap_wins_over_lead_window(): void {
        $short = $this->existingEntry('2026-07-20 09:20:00', '2026-07-20 09:25:00');
        $long = $this->existingEntry('2026-07-20 09:00:00', '2026-07-20 09:19:00');

        // Anruf 09:05–09:22: überlappt beide — 14 min mit $long, 2 min mit $short.
        $status = $this->service()->bookCall($this->organization, $this->config(), $this->makeCall('2026-07-20 09:05:00', 17), $this->owner->id);

        $this->assertSame('linked', $status);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => FritzboxPlugin::ID,
            'external_type' => 'call',
            'referenceable_id' => $long->id,
        ]);
        $this->assertDatabaseMissing('external_references', [
            'plugin_id' => FritzboxPlugin::ID,
            'external_type' => 'call',
            'referenceable_id' => $short->id,
        ]);
    }
}
