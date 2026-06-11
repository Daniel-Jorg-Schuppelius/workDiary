<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationCenterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\NotificationEvent;
use App\Models\{OpenIssue, User};
use App\Notifications\GenericEventNotification;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class NotificationCenterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function pushNotification(User $user, array $payload = []): void {
        $user->notify(new GenericEventNotification(
            NotificationEvent::OpenIssueAssigned,
            $payload + ['title' => 'Center-Test', 'message' => 'Hallo', 'url' => null],
            ['database'],
        ));
    }

    public function test_guest_is_redirected_to_login(): void {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_user_sees_own_notifications(): void {
        $this->pushNotification($this->user);

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Center-Test');
    }

    public function test_mark_all_as_read(): void {
        $this->pushNotification($this->user);
        $this->pushNotification($this->user);
        $this->assertSame(2, $this->user->unreadNotifications()->count());

        $this->actingAs($this->user)
            ->post(route('notifications.readAll'))
            ->assertRedirect();

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_mark_single_as_read(): void {
        $this->pushNotification($this->user);
        $notification = $this->user->notifications()->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_user_cannot_read_foreign_notification(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->pushNotification($other);
        $foreign = $other->notifications()->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('notifications.read', $foreign->id))
            ->assertNotFound();

        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_open_issue_assignment_creates_in_app_notification(): void {
        $actor = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $issue = OpenIssue::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $actor->id,
        ]);

        app(\App\Services\OpenIssue\OpenIssueService::class)->assign($issue, $this->user, $actor);

        $this->assertSame(1, $this->user->notifications()->count());
        $data = (array) $this->user->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::OpenIssueAssigned->value, $data['event'] ?? null);
    }
}
