<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexEligibilityListTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Models\Platform\User;
use App\Models\Time\FlexEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Gleitzeitberechtigungen je Mitglied: Sortierung nach Ende und Speichern am selben Starttag. */
final class FlexEligibilityListTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $member;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->member = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_open_period_sorts_like_the_latest_end_date(): void {
        $closed = $this->period('2026-01-01', '2026-03-31');
        $open = $this->period('2026-04-01', null);

        $ids = fn (string $dir): array => $this->actingAs($this->admin)
            ->get(route('users.flex-eligibility.index', ['user' => $this->member, 'sort' => 'valid_to', 'dir' => $dir]))
            ->assertOk()
            ->viewData('periods')->pluck('id')->all();

        $this->assertSame([$closed->id, $open->id], $ids('asc'));
        $this->assertSame([$open->id, $closed->id], $ids('desc'), 'Absteigend steht die laufende Periode oben.');
    }

    public function test_saving_the_same_start_day_again_updates_the_period(): void {
        foreach (['erste Fassung', 'zweite Fassung'] as $note) {
            $this->actingAs($this->admin)
                ->post(route('users.flex-eligibility.store', $this->member), ['valid_from' => '2026-05-01', 'note' => $note])
                ->assertRedirect();
        }

        $this->assertSame(['zweite Fassung'], FlexEligibility::query()->where('user_id', $this->member->id)->pluck('note')->all());
    }

    private function period(string $from, ?string $to): FlexEligibility {
        return FlexEligibility::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
            'valid_from' => $from,
            'valid_to' => $to,
        ]);
    }
}
