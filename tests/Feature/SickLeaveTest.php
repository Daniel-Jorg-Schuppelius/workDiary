<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeaveTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Sickness\SickLeaveKind;
use App\Http\Controllers\SickLeaveController;
use App\Models\{SickLeave, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SickLeaveTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        config()->set('sickness.attachment_required_from_day', 4);
        Storage::fake('local');
    }

    public function test_user_can_create_two_day_sick_leave_without_attachment(): void {
        $this->actingAs($this->user);

        $this->post(route('sick-leaves.store'), [
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
            'kind' => SickLeaveKind::Initial->value,
        ])->assertRedirect(route('duties.index', ['tab' => 'krank']));

        $this->assertDatabaseHas('sick_leaves', [
            'user_id' => $this->user->id,
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $sickLeave = SickLeave::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('2026-05-04', $sickLeave->start_date->toDateString());
        $this->assertSame('2026-05-05', $sickLeave->end_date->toDateString());
    }

    public function test_long_sick_leave_requires_au_file(): void {
        $this->actingAs($this->user);

        $this->from(route('sick-leaves.create'))
            ->post(route('sick-leaves.store'), [
                'start_date' => '2026-05-04',
                'end_date' => '2026-05-10',
                'kind' => SickLeaveKind::Initial->value,
            ])
            ->assertSessionHasErrors('au_file');

        $this->assertDatabaseCount('sick_leaves', 0);
    }

    public function test_long_sick_leave_succeeds_with_au_file(): void {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('au.pdf', 50, 'application/pdf');

        $this->post(route('sick-leaves.store'), [
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-10',
            'kind' => SickLeaveKind::Initial->value,
            'au_file' => $file,
        ])->assertRedirect();

        $sickLeave = SickLeave::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(1, $sickLeave->attachments()->count());
    }

    public function test_follow_up_chain_links_to_previous(): void {
        $this->actingAs($this->user);

        $initial = SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-06',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $this->post(route('sick-leaves.store'), [
            'start_date' => '2026-05-07',
            'end_date' => '2026-05-09',
            'kind' => SickLeaveKind::FollowUp->value,
            'follow_up_for_id' => $initial->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('sick_leaves', [
            'user_id' => $this->user->id,
            'kind' => SickLeaveKind::FollowUp->value,
            'follow_up_for_id' => $initial->id,
        ]);
    }

    public function test_follow_up_requires_previous_id(): void {
        $this->actingAs($this->user);

        $this->from(route('sick-leaves.create'))
            ->post(route('sick-leaves.store'), [
                'start_date' => '2026-05-07',
                'end_date' => '2026-05-09',
                'kind' => SickLeaveKind::FollowUp->value,
            ])
            ->assertSessionHasErrors('follow_up_for_id');
    }

    public function test_user_can_cancel_own_sick_leave(): void {
        $this->actingAs($this->user);

        $sickLeave = SickLeave::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->patch(route('sick-leaves.cancel', $sickLeave), [
            'cancel_reason' => 'Falsch erfasst',
        ])->assertRedirect();

        $sickLeave->refresh();
        $this->assertNotNull($sickLeave->cancelled_at);
        $this->assertSame('Falsch erfasst', $sickLeave->cancel_reason);
    }

    public function test_user_cannot_update_other_users_sick_leave(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $sickLeave = SickLeave::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user);
        $this->put(route('sick-leaves.update', $sickLeave), [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
            'kind' => SickLeaveKind::Initial->value,
        ])->assertForbidden();
    }

    public function test_attachment_download_requires_signed_url(): void {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('au.pdf', 50, 'application/pdf');
        $this->post(route('sick-leaves.store'), [
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-10',
            'kind' => SickLeaveKind::Initial->value,
            'au_file' => $file,
        ])->assertRedirect();

        /** @var SickLeave $sickLeave */
        $sickLeave = SickLeave::query()->where('user_id', $this->user->id)->firstOrFail();
        $attachment = $sickLeave->attachments()->first();

        // Unsigned access is forbidden.
        $this->get(route('sick-leaves.attachments.download', [
            'sick_leave' => $sickLeave->id,
            'attachment' => $attachment->id,
        ]))->assertForbidden();

        // Signed URL works.
        $signed = SickLeaveController::attachmentDownloadUrl($sickLeave, $attachment);
        $this->get($signed)->assertOk();
    }

    public function test_admin_can_create_sick_leave_for_other_user(): void {
        $this->actingAs($this->admin);

        $this->post(route('sick-leaves.store'), [
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
            'kind' => SickLeaveKind::Initial->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('sick_leaves', [
            'user_id' => $this->user->id,
            'recorded_by' => $this->admin->id,
        ]);
    }
}
