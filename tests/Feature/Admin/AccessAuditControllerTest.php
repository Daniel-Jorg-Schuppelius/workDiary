<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessAuditControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sicht auf Rollen- und Rechteänderungen (MVP-1302, `access.audit.view`).
 */
class AccessAuditControllerTest extends TestCase {
    use RefreshDatabase;

    public function test_regular_user_is_forbidden(): void {
        $this->actingAs(User::factory()->user()->create())
            ->get(route('admin.access.audit'))
            ->assertForbidden();
    }

    public function test_support_sees_access_changes_but_not_other_events(): void {
        $support = User::factory()->support()->create();
        $member = User::factory()->user()->create(['organization_id' => $support->organization_id, 'name' => 'Erika Muster']);
        $log = fn (string $event, array $changes) => AuditLog::query()->create([
            'organization_id' => $support->organization_id,
            'user_id' => $support->id,
            'event' => $event,
            'auditable_type' => MorphMap::stableKey(User::class),
            'auditable_id' => $member->id,
            'changes' => $changes,
        ]);
        $log('user.role.assigned', ['role' => 'teamleitung']);
        $log('user.permission.granted', ['permission' => 'invoice.create']);
        $log('support.access.granted', ['by' => 'platform']);

        $this->actingAs($support)
            ->get(route('admin.access.audit'))
            ->assertOk()
            ->assertSee('user.role.assigned')
            ->assertSee('user.permission.granted')
            ->assertSee('Erika Muster')
            ->assertDontSee('support.access.granted');
    }

    public function test_hub_and_audit_log_link_to_the_view(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.access.index'))
            ->assertOk()
            ->assertSee(route('admin.access.audit'), false);
        $this->actingAs($admin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee(route('admin.access.audit'), false);
    }
}
