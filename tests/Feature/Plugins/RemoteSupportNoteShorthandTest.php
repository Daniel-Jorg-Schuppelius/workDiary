<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportNoteShorthandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Models\{Asset, Customer, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\{RemoteSession, TeamViewerClient};
use App\Plugins\RemoteSupport\RemoteSupportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zeit-Kürzel in der Sitzungsnotiz („+1h", „2h extra", „seit 8h") ziehen den
 * Buchungsbeginn des erzeugten Zeiteintrags vor.
 */
class RemoteSupportNoteShorthandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
        ]);
        (new RemoteSupportService)->setRemoteId($this->asset, TeamViewerClient::ID, '424242424');
    }

    /** Bucht eine Sitzung 10:00–11:00 mit der übergebenen Notiz und liefert den Eintrag. */
    private function bookWithNote(?string $note, string $sessionId): TimeEntry {
        $session = new RemoteSession(
            provider: TeamViewerClient::ID,
            sessionId: $sessionId,
            remoteId: '424242424',
            startedAt: CarbonImmutable::parse('2026-07-20 10:00:00'),
            endedAt: CarbonImmutable::parse('2026-07-20 11:00:00'),
            note: $note,
        );

        $result = (new RemoteSupportService)->importSessions(
            $this->organization,
            ['default_user_id' => null, 'default_billable' => true],
            [$session],
        );
        $this->assertSame(1, $result['created']);

        return TimeEntry::query()->latest('id')->firstOrFail();
    }

    public function test_plus_hour_extends_start(): void {
        $entry = $this->bookWithNote('Serverpflege +1h', 'n1');
        $this->assertSame('2026-07-20 09:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 11:00:00', $entry->ended_at?->format('Y-m-d H:i:s'));
    }

    public function test_plus_minutes_extends_start(): void {
        $entry = $this->bookWithNote('+30min Telefonat vorab', 'n2');
        $this->assertSame('2026-07-20 09:30:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_extra_word_form_extends_start(): void {
        $entry = $this->bookWithNote('Update eingespielt, 2h extra', 'n3');
        $this->assertSame('2026-07-20 08:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_decimal_hours_with_comma(): void {
        $entry = $this->bookWithNote('+1,5h Vorbereitung', 'n4');
        $this->assertSame('2026-07-20 08:30:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_seit_sets_absolute_start(): void {
        $entry = $this->bookWithNote('läuft seit 8h', 'n5');
        $this->assertSame('2026-07-20 08:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_seit_with_minutes(): void {
        $entry = $this->bookWithNote('seit 8:30 dran', 'n6');
        $this->assertSame('2026-07-20 08:30:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_seit_wins_over_duration(): void {
        $entry = $this->bookWithNote('seit 8h +1h', 'n7');
        $this->assertSame('2026-07-20 08:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_seit_after_session_start_is_ignored(): void {
        $entry = $this->bookWithNote('seit 12h', 'n8');
        $this->assertSame('2026-07-20 10:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_extra_minutes_are_capped(): void {
        $entry = $this->bookWithNote('+100h', 'n9');
        // Deckel note_extra_max_minutes (Default 480 Min. = 8 h).
        $this->assertSame('2026-07-20 02:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }

    public function test_note_without_shorthand_keeps_start(): void {
        $entry = $this->bookWithNote('Drucker eingerichtet', 'n10');
        $this->assertSame('2026-07-20 10:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
    }
}
