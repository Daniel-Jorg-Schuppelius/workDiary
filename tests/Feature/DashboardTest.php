<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_for_user_with_kpis(): void
    {
        $user = User::factory()->user()->create();
        DiaryEntry::factory()->for($user)->count(3)->create(['status' => 2, 'is_archived' => false]);
        DiaryEntry::factory()->for($user)->count(2)->create(['status' => 1, 'is_archived' => false]);
        DiaryEntry::factory()->for($user)->create(['status' => -1, 'is_archived' => false]);

        $now = CarbonImmutable::now();
        OnCallShift::factory()->for($user)->create([
            'start_at' => $now->subHour(),
            'end_at' => $now->addHours(7),
        ]);
        OnCallShift::factory()->for($user)->create([
            'start_at' => $now->addDays(2),
            'end_at' => $now->addDays(2)->addHours(8),
        ]);
        EmergencyAssignment::factory()->for($user)->create([
            'start_at' => $now->addDay(),
            'end_at' => $now->addDay()->addHours(2),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee(__('Dashboard'))
            ->assertSee(__('Meine offenen Einträge'))
            ->assertSee(__('Heute'))
            ->assertSee(__('Nächste Schichten'))
            ->assertDontSee(__('Team'));
    }

    public function test_dashboard_shows_team_section_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->user()->create();
        DiaryEntry::factory()->for($other)->count(2)->create(['status' => 2, 'is_archived' => false]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Team'))
            ->assertSee(__('Offen (Team)'))
            ->assertSee(__('Mitarbeitende'));
    }

    public function test_dashboard_lists_recent_comments_on_own_entries(): void
    {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        Comment::factory()->for($other)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'body' => 'Wichtiger Hinweis von Kollege']);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Wichtiger Hinweis von Kollege');
    }

    public function test_home_redirects_authenticated_new_mode_user_to_dashboard(): void
    {
        $user = User::factory()->user()->create();
        $this->actingAs($user)
            ->withSession(['work_mode' => 'new'])
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
