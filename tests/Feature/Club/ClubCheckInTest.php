<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCheckInTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubAttendanceStatus, ClubEventVisibility};
use App\Models\Club\{ClubAttendanceRecord, ClubAttendanceSheet, ClubGroup, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\{ClubEventService, ClubGroupService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1004: QR-Selbst-Check-in für Vereinstermine. */
final class ClubCheckInTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_members_check_themselves_in_within_the_window(): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();
        $this->travelTo(CarbonImmutable::parse('2026-09-15 17:30', 'Europe/Berlin'));
        $group = ClubGroup::factory()->create();
        $event = app(ClubEventService::class)->create($this->organization, $admin, [
            'title' => 'Training', 'kind' => 'training', 'visibility' => ClubEventVisibility::Groups->value, 'club_group_ids' => [$group->id],
            'started_at' => '2026-09-15 16:00:00', 'ended_at' => '2026-09-15 18:00:00', 'timezone' => 'Europe/Berlin',
        ]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $member = ClubMember::factory()->aged(30)->create(['user_id' => $user->id]);
        app(ClubGroupService::class)->admit($group, $member, CarbonImmutable::today()->subMonth(), $admin);
        $stranger = ClubMember::factory()->aged(30)->create();

        $this->actingAs($admin)->post(route('club.events.checkin-code', $event))->assertRedirect(route('club.events.show', $event));
        $code = (string) $event->refresh()->clubDetails?->checkin_code;
        $this->assertSame(32, strlen($code));
        $this->actingAs($admin)->get(route('club.events.show', $event))->assertOk()->assertSee('data:image/svg+xml', false);

        $this->actingAs($user)->get(route('club.checkin.show', $code))->assertOk()->assertSee($member->fullName());
        // Anzeigen legt nichts an — weder der Check-in noch die Liste der Leitung.
        $this->actingAs($admin)->get(route('club.events.attendance.show', $event))->assertOk();
        $this->assertSame(0, ClubAttendanceSheet::query()->count());
        $this->actingAs($user)->post(route('club.checkin.store', $code), ['member' => $stranger->sqid])->assertForbidden();
        $this->actingAs($user)->post(route('club.checkin.store', $code), ['member' => $member->sqid])->assertRedirect(route('club.checkin.show', $code));
        $this->assertSame(1, ClubAttendanceSheet::query()->count());
        $this->assertSame(ClubAttendanceStatus::Present, ClubAttendanceRecord::query()->where('club_member_id', $member->id)->sole()->status);
        $this->actingAs($user)->get(route('club.checkin.show', $code))->assertSee(__('club.checkin.status.checked_in'));

        $this->travelTo(CarbonImmutable::parse('2026-09-15 20:30', 'Europe/Berlin'));
        $this->actingAs($user)->post(route('club.checkin.store', $code), ['member' => $member->sqid])->assertSessionHasErrors('checkin');

        $this->actingAs($admin)->post(route('club.events.checkin-code', $event));
        $this->actingAs($user)->get(route('club.checkin.show', $code))->assertNotFound();
    }
}
