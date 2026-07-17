<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFilterPresetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\UserFilterPreset;
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
    public function guest_redirected_to_login(): void {
        $this->get(route('filter-presets.index'))->assertRedirect(route('login'));
    }
}
