<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginConnectionActionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Plugins\CalDav\Models\CalDavConnection;
use App\Plugins\Webdav\Models\WebdavConnection;
use App\Plugins\Zammad\Models\ZammadConnection;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Gemeinsame Admin-Aktionen der Plugins (ManagesPluginConnection, Vollscan 2026-10-21). */
final class PluginConnectionActionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_manual_run_needs_an_active_connection_and_is_queued_with_audit(): void {
        Queue::fake();
        $this->actingAs($this->admin)->post(route('admin.caldav.publish'))->assertSessionHas('error');
        Queue::assertNothingPushed();

        $connection = CalDavConnection::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Nextcloud', 'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc', 'app_password' => 'secret', 'calendar_path' => 'calendars/team/zeiten', 'active' => true,
        ]);
        $this->actingAs($this->admin)->post(route('admin.caldav.publish'))->assertSessionHas('success');
        Queue::assertPushed(QueuedCommand::class, 1);
        $this->assertTrue(AuditLog::query()->where('event', 'caldav.publish_manual')->where('auditable_id', $connection->id)->exists());

        ZammadConnection::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Helpdesk', 'base_url' => 'https://zammad.example.org', 'api_token' => 'secret', 'active' => true,
        ]);
        $this->actingAs($this->admin)->post(route('admin.zammad.sync'))->assertSessionHas('success');
        Queue::assertPushed(QueuedCommand::class, 2);
    }

    public function test_disconnect_deactivates_and_audits(): void {
        $webdav = WebdavConnection::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Nextcloud', 'base_url' => 'https://cloud.example.com/remote.php/dav/files/svc/WorkDiary',
            'username' => 'svc', 'app_password' => 'secret', 'default_folder' => 'Dokumente', 'active' => true,
        ]);
        $zammad = ZammadConnection::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Helpdesk', 'base_url' => 'https://zammad.example.org', 'api_token' => 'secret', 'active' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.webdav.disconnect'))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.zammad.disconnect'))->assertSessionHas('success');

        $this->assertFalse((bool) $webdav->fresh()?->active);
        $this->assertSame('secret', $webdav->fresh()?->app_password, 'Zugangsdaten bleiben für ein erneutes Verbinden');
        $this->assertFalse((bool) $zammad->fresh()?->active);
        $this->assertTrue(AuditLog::query()->where('event', 'zammad.disconnected')->where('auditable_id', $zammad->id)->exists());

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->post(route('admin.caldav.disconnect'))->assertForbidden();
    }
}
