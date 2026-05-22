<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryWebRoutesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryWebRoutesTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_owner_can_view_and_edit_own_entry(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create(['content' => 'Eigener Eintrag']);

        $this->actingAs($user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee('Eigener Eintrag');

        $this->actingAs($user)
            ->get(route('diary.edit', $entry))
            ->assertOk();
    }

    public function test_other_user_cannot_edit_foreign_entry(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('diary.edit', $entry))
            ->assertForbidden();
    }
}
