<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineSyncAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Sync\SyncCommandStatus;
use App\Models\{Organization, SyncCommand, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Admin-Sicht auf die Offline-Synchronisierung (Feature 004-Restpunkt).
 *
 * Kern der Prüfung: **Abgewiesene Befehle sind sichtbar** — sie bedeuten,
 * dass eine Offline-Erfassung nicht im Bestand gelandet ist — und die
 * Mandantengrenze hält.
 */
final class OfflineSyncAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function command(string $status, array $attributes = []): SyncCommand {
        return SyncCommand::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.clock-in',
            'result_status' => $status,
        ], $attributes));
    }

    public function test_page_lists_commands_with_their_result(): void {
        $this->command(SyncCommandStatus::Applied->value);
        $this->command(SyncCommandStatus::Rejected->value, [
            'type' => 'comment.diary',
            'result_errors' => ['payload' => ['Auftrag nicht gefunden.']],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.offline-sync.index'))
            ->assertOk()
            ->assertSee('Übernommen')
            ->assertSee('Abgewiesen')
            ->assertSee('Auftrag nicht gefunden.');
    }

    public function test_status_filter_narrows_the_list_but_not_the_counters(): void {
        $this->command(SyncCommandStatus::Applied->value);
        $this->command(SyncCommandStatus::Rejected->value, ['type' => 'comment.diary']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.offline-sync.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('comment.diary');

        // Der Zähler des anderen Ergebnisses bleibt sichtbar - die Frage
        // „gibt es irgendwo ein Problem?" darf kein Filter verdecken.
        $response->assertSee('Übernommen (1)');
        $this->assertStringNotContainsString(
            'attendance.clock-in</td>',
            (string) $response->getContent(),
        );
    }

    public function test_foreign_organization_commands_stay_invisible(): void {
        $other = Organization::factory()->create();
        $foreignUser = User::factory()->create(['organization_id' => $other->id]);
        SyncCommand::query()->create([
            'organization_id' => $other->id,
            'user_id' => $foreignUser->id,
            'client_uuid' => (string) Str::uuid(),
            'type' => 'attendance.clock-out',
            'result_status' => SyncCommandStatus::Applied->value,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.offline-sync.index'))
            ->assertOk()
            ->assertDontSee('attendance.clock-out');
    }

    public function test_plain_user_is_denied(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.offline-sync.index'))
            ->assertForbidden();
    }
}
