<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftFormTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Diary;

use App\Models\Diary\OnCallShift;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Bereitschaftsformular: die Benutzerauswahl sendet die Sqid, keine Datenbank-ID (Konsolidierungs-Audit 2026-10, k4-24). */
final class OnCallShiftFormTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_user_select_carries_sqids_and_the_shift_is_stored_for_the_chosen_user(): void {
        $admin = $this->orgAdmin();
        $colleague = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kollegin Bereitschaft']);

        $this->actingAs($admin)->get(route('shifts.create'))->assertOk()
            ->assertSee('value="' . $colleague->sqid . '"', false)
            ->assertDontSee('value="' . $colleague->id . '"', false);

        $this->actingAs($admin)->post(route('shifts.store'), [
            'user_id' => $colleague->sqid,
            'start_at' => '2026-10-10T18:00',
            'end_at' => '2026-10-11T06:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($colleague->id, OnCallShift::query()->firstOrFail()->user_id);
    }
}
