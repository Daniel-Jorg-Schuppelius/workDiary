<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportCallMergeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Models\{Asset, Customer, TimeEntry, User};
use App\Plugins\Fritzbox\FritzboxImportService;
use App\Plugins\Fritzbox\Sources\FritzboxCall;
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, RemoteSession};
use App\Plugins\RemoteSupport\{RemoteSupportPlugin, RemoteSupportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Gegenstück zum FritzBox-Lead-Fenster: Existiert der Telefonat-Eintrag
 * bereits, verschmilzt die später synchronisierte Fernwartungssitzung mit ihm
 * (Ende wird auf das Sitzungsende gezogen) statt doppelt zu buchen. Fremd
 * erfasste Zeiten und exportierte Einträge bleiben unangetastet.
 */
class RemoteSupportCallMergeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private Customer $customer;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '02219567000',
        ]);
        $this->asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $this->customer->id,
        ]);
        (new RemoteSupportService)->setRemoteId($this->asset, AnyDeskClient::ID, '123456789');
    }

    /** Legt per FritzBox-Import einen Telefonat-Eintrag des Kunden an. */
    private function bookCallEntry(string $start, int $minutes): TimeEntry {
        $startedAt = CarbonImmutable::parse($start, 'UTC');
        $call = new FritzboxCall(
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
        $config = ['default_billable' => true, 'default_user_id' => null, 'min_call_minutes' => 2, 'call_lead_minutes' => 15, 'own_number_allowlist' => [], 'type3_outgoing' => false];

        $status = (new FritzboxImportService)->bookCall($this->organization, $config, $call, $this->owner->id);
        $this->assertSame('created', $status);

        return TimeEntry::query()->withoutGlobalScopes()->orderByDesc('id')->firstOrFail();
    }

    private function bookSession(string $start, string $end): string {
        $session = new RemoteSession(
            provider: AnyDeskClient::ID,
            sessionId: 'sess-' . $start,
            remoteId: '123456789',
            startedAt: CarbonImmutable::parse($start, 'UTC'),
            endedAt: CarbonImmutable::parse($end, 'UTC'),
        );

        return (new RemoteSupportService)->bookSession(
            $this->organization,
            ['default_billable' => true],
            $session,
            $this->owner->id,
        );
    }

    public function test_session_after_call_entry_merges_within_lead_window(): void {
        $entry = $this->bookCallEntry('2026-07-20 08:45:00', 5); // endet 08:50

        $status = $this->bookSession('2026-07-20 09:00:00', '2026-07-20 10:00:00'); // Lücke 10 min ≤ 15

        $this->assertSame('linked', $status);
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());

        $fresh = $entry->fresh();
        $this->assertSame('2026-07-20 08:45:00', $fresh->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 10:00:00', $fresh->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame(75, $fresh->minutes);

        // Sitzung hängt als Nachweis am Telefonat-Eintrag.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => RemoteSupportPlugin::ID,
            'external_type' => 'session',
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_session_outside_lead_window_creates_own_entry(): void {
        $entry = $this->bookCallEntry('2026-07-20 08:45:00', 5); // endet 08:50

        $status = $this->bookSession('2026-07-20 09:10:00', '2026-07-20 10:00:00'); // Lücke 20 min > 15

        $this->assertSame('created', $status);
        $this->assertSame(2, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame('2026-07-20 08:50:00', $entry->fresh()->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_partially_overlapping_call_entry_is_extended(): void {
        $entry = $this->bookCallEntry('2026-07-20 08:50:00', 10); // endet 09:00

        $status = $this->bookSession('2026-07-20 08:58:00', '2026-07-20 10:00:00');

        $this->assertSame('linked', $status);
        $this->assertSame(1, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame('2026-07-20 10:00:00', $entry->fresh()->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_manual_entry_is_never_extended(): void {
        // Manuell erfasste Zeit ohne FritzBox-Referenz: bleibt autoritativ.
        $manual = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->customer->defaultProjectOrCreate()->id,
            'user_id' => $this->owner->id,
            'started_at' => CarbonImmutable::parse('2026-07-20 08:00:00', 'UTC'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 08:55:00', 'UTC'),
            'description' => 'Vor-Ort-Termin',
        ]);

        // Lücken-Fall: kein Telefonat-Eintrag → eigene Buchung wie bisher.
        $status = $this->bookSession('2026-07-20 09:00:00', '2026-07-20 10:00:00');
        $this->assertSame('created', $status);
        $this->assertSame(2, TimeEntry::query()->withoutGlobalScopes()->count());

        // Überlappungs-Fall: verknüpfen ja, verlängern nein (Bestandsverhalten).
        $status = $this->bookSession('2026-07-20 08:30:00', '2026-07-20 08:58:00');
        $this->assertSame('linked', $status);
        $this->assertSame('2026-07-20 08:55:00', $manual->fresh()->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_exported_call_entry_is_not_merged(): void {
        $entry = $this->bookCallEntry('2026-07-20 08:45:00', 5);
        $entry->forceFill(['exported' => true])->save();

        $status = $this->bookSession('2026-07-20 09:00:00', '2026-07-20 10:00:00');

        $this->assertSame('created', $status); // Sitzungszeit darf nicht im abgerechneten Eintrag verschwinden
        $this->assertSame(2, TimeEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame('2026-07-20 08:50:00', $entry->fresh()->ended_at->format('Y-m-d H:i:s'));
    }
}
