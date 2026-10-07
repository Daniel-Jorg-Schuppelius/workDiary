<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DialogFragmentPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Issue #106: Speichern im Arbeitszeit-Modell führte auf das nackte
 * Dialog-Fragment (ohne Layout und CSS). Der Folgedialog kehrt jetzt in die
 * Mitarbeitermaske zurück; wer ein Fragment direkt aufruft, bekommt eine Seite.
 */
class DialogFragmentPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const NAVIGATION = ['Sec-Fetch-Dest' => 'document'];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function member(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_direct_navigation_to_a_fragment_renders_a_page(): void {
        $member = $this->member();

        $this->actingAs($this->orgAdmin())
            ->withHeaders(self::NAVIGATION)
            ->get(route('users.work-schedule.edit', $member))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee('data-dialog-page', false)
            ->assertSee(route('users.work-schedule.update', $member), false);
    }

    /** Der Service Worker reicht Navigationen per fetch weiter (Sec-Fetch-Dest: empty). */
    public function test_navigation_through_the_service_worker_renders_a_page(): void {
        $member = $this->member();

        $this->actingAs($this->orgAdmin())
            ->withHeaders(['Sec-Fetch-Dest' => 'empty', 'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8'])
            ->get(route('users.work-schedule.edit', $member))
            ->assertOk()
            ->assertSee('data-dialog-page', false);

        $this->actingAs($this->orgAdmin())
            ->withHeaders(['Sec-Fetch-Dest' => 'empty', 'Accept' => '*/*'])
            ->get(route('users.work-schedule.edit', $member))
            ->assertOk()
            ->assertDontSee('<html', false);
    }

    public function test_dialog_host_and_tests_get_the_bare_fragment(): void {
        $admin = $this->orgAdmin();
        $member = $this->member();

        $this->actingAs($admin)->get(route('users.work-schedule.edit', $member))
            ->assertOk()->assertDontSee('<html', false);

        $this->actingAs($admin)->withHeaders(self::NAVIGATION)
            ->get(route('users.work-schedule.edit', [$member, 'dialog' => 1]))
            ->assertOk()->assertDontSee('<html', false);

        $this->actingAs($admin)->withHeaders(self::NAVIGATION + ['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('users.work-schedule.edit', $member))
            ->assertOk()->assertDontSee('<html', false);
    }

    public function test_full_pages_are_not_wrapped_twice(): void {
        $this->actingAs($this->orgAdmin())
            ->withHeaders(self::NAVIGATION)
            ->get(route('org.members.index'))
            ->assertOk()
            ->assertDontSee('data-dialog-page', false);
    }

    public function test_saving_the_work_schedule_as_follow_up_dialog_returns_to_the_member_dialog(): void {
        $member = $this->member();

        $this->actingAs($this->orgAdmin())
            ->withHeaders([
                'X-Entry-Dialog' => '1',
                'X-Entry-Dialog-Stacked' => '1',
                'X-Entry-Dialog-Url' => route('users.work-schedule.edit', $member),
                'Accept' => 'application/json',
            ])
            ->put(route('users.work-schedule.update', $member), [
                'weekly_minutes' => 2400,
                'daily_target_minutes' => 480,
                'working_days' => [1, 2, 3, 4, 5],
                'break_after_minutes' => 360,
                'break_minutes' => 30,
                'valid_from' => '2026-01-01',
            ])
            ->assertOk()
            ->assertJsonPath('stay', true)
            ->assertJsonPath('messages.0.message', __('Arbeitszeit-Modell gespeichert.'));
    }

    public function test_member_dialog_marks_the_work_schedule_as_follow_up_dialog(): void {
        $member = $this->member();

        $this->actingAs($this->orgAdmin())
            ->get(route('org.members.edit', $member))
            ->assertOk()
            ->assertSee('data-entry-refresh="weekly-hours"', false)
            ->assertSee('href="' . route('users.work-schedule.edit', $member) . '" data-entry-modal-trigger data-entry-modal-stack', false);
    }
}
