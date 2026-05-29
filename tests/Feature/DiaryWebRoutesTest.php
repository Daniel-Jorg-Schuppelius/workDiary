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

use App\Models\{Customer, DiaryEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryWebRoutesTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_store_persists_customer_supplied_as_sqid(): void {
        $user = User::factory()->user()->create();
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);

        $this->actingAs($user)
            ->post(route('diary.store'), [
                'content' => 'Auftrag mit Kunde',
                'status' => 2,
                'start_at' => '2030-01-15 09:00:00',
                'end_at' => '2030-01-15 10:00:00',
                'customer_id' => $customer->sqid,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('diary_entries', [
            'content' => 'Auftrag mit Kunde',
            'customer_id' => $customer->id,
        ]);
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
