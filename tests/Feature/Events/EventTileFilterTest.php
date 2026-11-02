<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventTileFilterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Calendar\Event;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kacheln der Terminliste führen zu dem, was sie zählen (ux-pattern-katalog §4,
 * Vollscan 2026-11-02 gap-06): „Anstehend“ und „Heute“ filtern über `show`,
 * „Pflichtschulungen“ über den vorhandenen Schalter.
 */
final class EventTileFilterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
        $this->setUpOrganization(['timezone' => 'UTC']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function event(string $title, string $start, bool $mandatory = false): Event {
        return Event::factory()->create([
            'organization_id' => $this->organization->id, 'title' => $title, 'is_mandatory' => $mandatory,
            'started_at' => $start, 'ended_at' => \Carbon\CarbonImmutable::parse($start)->addHours(2), 'cancelled_at' => null,
        ]);
    }

    public function test_tiles_link_to_their_filter(): void {
        $this->event('Sicherheitsunterweisung', '2026-11-10 09:00:00', mandatory: true);

        $this->actingAs($this->admin)->get(route('events.index'))->assertOk()
            ->assertSee(route('events.index', ['show' => 'upcoming']))
            ->assertSee(route('events.index', ['show' => 'today']))
            ->assertSee(route('events.index', ['show' => 'upcoming', 'only_mandatory' => 1]))
            ->assertDontSee('ring-primary/40');
    }

    public function test_upcoming_and_today_filter_the_list(): void {
        $this->event('Rückblick gestern', '2026-11-01 09:00:00');
        $this->event('Teamrunde heute', '2026-11-02 14:00:00');
        $this->event('Messe nächste Woche', '2026-11-10 09:00:00');

        $this->actingAs($this->admin)->get(route('events.index', ['show' => 'upcoming']))->assertOk()
            ->assertSee('Teamrunde heute')->assertSee('Messe nächste Woche')->assertDontSee('Rückblick gestern')
            ->assertSee('ring-primary/40')->assertSee('<option value="upcoming" selected', false);

        $this->actingAs($this->admin)->get(route('events.index', ['show' => 'today']))->assertOk()
            ->assertSee('Teamrunde heute')->assertDontSee('Messe nächste Woche')->assertDontSee('Rückblick gestern');
    }

    public function test_mandatory_tile_narrows_upcoming_events(): void {
        $this->event('Messe nächste Woche', '2026-11-10 09:00:00');
        $this->event('Sicherheitsunterweisung', '2026-11-12 09:00:00', mandatory: true);

        $this->actingAs($this->admin)->get(route('events.index', ['show' => 'upcoming', 'only_mandatory' => 1]))->assertOk()
            ->assertSee('Sicherheitsunterweisung')->assertDontSee('Messe nächste Woche')
            // Nur die Pflicht-Kachel ist aktiv, nicht „Anstehend“: die Markierung steht erst hinter der Kachel „Heute“.
            ->assertSeeInOrder(['Heute', 'ring-primary/40', 'Pflichtschulungen']);
    }
}
