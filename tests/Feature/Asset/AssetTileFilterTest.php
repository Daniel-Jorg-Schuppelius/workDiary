<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetTileFilterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetStatus;
use App\Enums\User\UserRole;
use App\Models\Asset\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kacheln der Asset-Liste führen zu dem, was sie zählen (ux-pattern-katalog §4,
 * Vollscan 2026-11-02 gap-06): „In Wartung/Reparatur“ fasst zwei Status, die
 * die Status-Auswahl einzeln kennt; die Kachelwerte bleiben beim Filtern stehen.
 */
class AssetTileFilterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    private function asset(string $name, AssetStatus $status): Asset {
        return Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => $name, 'status' => $status->value]);
    }

    public function test_tiles_link_to_their_filter_and_the_maintenance_tile_filters_two_statuses(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $this->asset('Bohrhammer aktiv', AssetStatus::Active);
        $this->asset('Kompressor in Wartung', AssetStatus::InMaintenance);
        $this->asset('Stapler in Reparatur', AssetStatus::InRepair);
        $this->asset('Kran gesperrt', AssetStatus::Blocked);

        $this->actingAs($user)->get(route('assets.index'))->assertOk()
            ->assertSee(route('assets.index', ['show' => 'maintenance']))
            ->assertSee(route('assets.index', ['status' => AssetStatus::Blocked->value]))
            ->assertDontSee('ring-primary/40');

        $this->actingAs($user)->get(route('assets.index', ['show' => 'maintenance']))->assertOk()
            ->assertSee('Kompressor in Wartung')->assertSee('Stapler in Reparatur')
            ->assertDontSee('Bohrhammer aktiv')->assertDontSee('Kran gesperrt')
            // Die aktive Kachel ist markiert, der Schalter in der Filterleiste gesetzt; die Zahlen bleiben stehen.
            ->assertSee('ring-primary/40')
            ->assertSee('name="show"', false)
            ->assertSeeInOrder(['In Wartung/Reparatur', '2']);
    }

    public function test_blocked_tile_uses_the_existing_status_filter(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $this->asset('Bohrhammer aktiv', AssetStatus::Active);
        $this->asset('Kran gesperrt', AssetStatus::Blocked);

        $this->actingAs($user)->get(route('assets.index', ['status' => AssetStatus::Blocked->value]))->assertOk()
            ->assertSee('Kran gesperrt')->assertDontSee('Bohrhammer aktiv')->assertSee('ring-primary/40');
    }

    public function test_unknown_show_value_lists_everything(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $this->asset('Bohrhammer aktiv', AssetStatus::Active);
        $this->asset('Kompressor in Wartung', AssetStatus::InMaintenance);

        $this->actingAs($user)->get(route('assets.index', ['show' => 'alles']))->assertOk()
            ->assertSee('Bohrhammer aktiv')->assertSee('Kompressor in Wartung')->assertDontSee('ring-primary/40');
    }
}
