<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarFeedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Calendar;

use App\Enums\Vacation\VacationStatus;
use App\Models\User;
use App\Models\Vacation;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CalendarFeedTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_rotate_creates_token_and_settings_page_shows_url(): void {
        $this->actingAs($this->user)
            ->post(route('account.calendar.rotate'))
            ->assertRedirect(route('account.calendar.show'));

        $this->user->refresh();
        $this->assertNotEmpty($this->user->calendar_feed_token);
        $this->assertGreaterThanOrEqual(32, strlen((string) $this->user->calendar_feed_token));

        $this->actingAs($this->user)
            ->get(route('account.calendar.show'))
            ->assertOk()
            ->assertSee($this->user->calendar_feed_token);
    }

    public function test_feed_returns_ics_with_approved_vacation(): void {
        $this->user->calendar_feed_token = Str::random(48);
        $this->user->save();

        Vacation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'type' => 'vacation',
            'status' => VacationStatus::Approved->value,
        ]);

        $response = $this->get(route('calendar.feed.personal', ['token' => $this->user->calendar_feed_token]));

        $response->assertOk();
        $this->assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('20260810', $body);
        // DTEND exklusiv → 13.08.
        $this->assertStringContainsString('20260813', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
    }

    public function test_invalid_token_returns_404(): void {
        $this->get(route('calendar.feed.personal', ['token' => str_repeat('x', 48)]))
            ->assertNotFound();
    }

    public function test_short_token_returns_404(): void {
        $this->get('/calendar/feed/abc.ics')->assertNotFound();
    }

    public function test_revoke_clears_token(): void {
        $this->user->calendar_feed_token = Str::random(48);
        $this->user->save();

        $this->actingAs($this->user)
            ->delete(route('account.calendar.revoke'))
            ->assertRedirect(route('account.calendar.show'));

        $this->assertNull($this->user->fresh()?->calendar_feed_token);
    }
}
