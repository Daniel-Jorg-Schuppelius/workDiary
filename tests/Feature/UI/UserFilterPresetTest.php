<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFilterPresetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\Platform\UserFilterPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class UserFilterPresetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    #[Test]
    public function index_lists_only_own_presets(): void {
        $user = $this->orgUser();
        $other = $this->orgUser();

        UserFilterPreset::create([
            'user_id' => $user->id,
            'scope' => 'diary',
            'name' => 'My Preset',
            'query' => ['status' => 'open'],
        ]);
        UserFilterPreset::create([
            'user_id' => $other->id,
            'scope' => 'diary',
            'name' => 'Other Preset',
            'query' => [],
        ]);

        $this->actingAs($user)
            ->get(route('filter-presets.index'))
            ->assertOk()
            ->assertSee('My Preset')
            ->assertDontSee('Other Preset');
    }

    #[Test]
    public function store_creates_preset_and_unmarks_other_defaults_in_scope(): void {
        $user = $this->orgUser();
        $existing = UserFilterPreset::create([
            'user_id' => $user->id,
            'scope' => 'diary',
            'name' => 'Old default',
            'query' => [],
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->post(route('filter-presets.store'), [
                'scope' => 'diary',
                'name' => 'New default',
                'query' => ['status' => 'open', 'tag' => 'foo'],
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_filter_presets', [
            'user_id' => $user->id,
            'name' => 'New default',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('user_filter_presets', [
            'id' => $existing->id,
            'is_default' => false,
        ]);
    }

    #[Test]
    public function destroy_forbidden_for_foreign_preset(): void {
        $owner = $this->orgUser();
        $intruder = $this->orgUser();
        $preset = UserFilterPreset::create([
            'user_id' => $owner->id,
            'scope' => 'diary',
            'name' => 'Owned',
            'query' => [],
        ]);

        $this->actingAs($intruder)
            ->delete(route('filter-presets.destroy', $preset))
            ->assertForbidden();
    }

    #[Test]
    public function user_menu_links_the_presets_page(): void {
        $this->actingAs($this->orgUser())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('filter-presets.index'), false);
    }

    #[Test]
    public function index_offers_the_edit_dialog(): void {
        $user = $this->orgUser();
        $preset = UserFilterPreset::create([
            'user_id' => $user->id,
            'scope' => 'diary',
            'name' => 'Offene Einträge',
            'query' => ['status' => 'open'],
        ]);

        $this->actingAs($user)
            ->get(route('filter-presets.index'))
            ->assertOk()
            ->assertSee(route('filter-presets.edit', $preset), false);

        $this->actingAs($user)
            ->get(route('filter-presets.edit', $preset))
            ->assertOk()
            ->assertSee(route('filter-presets.update', $preset), false)
            ->assertSee('Offene Einträge');
    }

    #[Test]
    public function update_renames_keeps_the_query_and_moves_the_default(): void {
        $user = $this->orgUser();
        $oldDefault = UserFilterPreset::create([
            'user_id' => $user->id,
            'scope' => 'diary',
            'name' => 'Bisheriger Standard',
            'query' => [],
            'is_default' => true,
        ]);
        $otherScope = UserFilterPreset::create([
            'user_id' => $user->id,
            'scope' => 'invoices',
            'name' => 'Rechnungen',
            'query' => [],
            'is_default' => true,
        ]);
        $preset = UserFilterPreset::create([
            'user_id' => $user->id,
            'scope' => 'diary',
            'name' => 'Offene Einträge',
            'query' => ['status' => 'open'],
        ]);

        // Wie der Dialog sendet: Bereich versteckt, Filterwerte gar nicht.
        $this->actingAs($user)
            ->from(route('filter-presets.index'))
            ->put(route('filter-presets.update', $preset), [
                'scope' => 'diary',
                'name' => 'Offen und dringend',
                'sort_order' => 3,
                'is_default' => '1',
            ])
            ->assertRedirect(route('filter-presets.index'))
            ->assertSessionHas('status', __('Filter aktualisiert.'));

        $preset->refresh();
        $this->assertSame('Offen und dringend', $preset->name);
        $this->assertSame(3, $preset->sort_order);
        $this->assertTrue($preset->is_default);
        $this->assertSame(['status' => 'open'], $preset->query);
        $this->assertFalse($oldDefault->refresh()->is_default);
        $this->assertTrue($otherScope->refresh()->is_default, 'Der Standard eines anderen Bereichs bleibt.');
    }

    #[Test]
    public function edit_and_update_forbidden_for_foreign_preset(): void {
        $owner = $this->orgUser();
        $intruder = $this->orgUser();
        $preset = UserFilterPreset::create([
            'user_id' => $owner->id,
            'scope' => 'diary',
            'name' => 'Owned',
            'query' => [],
        ]);

        $this->actingAs($intruder)
            ->get(route('filter-presets.edit', $preset))
            ->assertForbidden();
        $this->actingAs($intruder)
            ->put(route('filter-presets.update', $preset), ['scope' => 'diary', 'name' => 'Gekapert'])
            ->assertForbidden();

        $this->assertSame('Owned', $preset->refresh()->name);
    }

    #[Test]
    public function guest_redirected_to_login(): void {
        $this->get(route('filter-presets.index'))->assertRedirect(route('login'));
    }
}
