<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportAccessAuditControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\{AuditLog, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAccessAuditControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_requires_authentication(): void {
        $this->get(route('admin.support.access-audit.index'))->assertRedirect(route('login'));
    }

    public function test_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)
            ->get(route('admin.support.access-audit.index'))
            ->assertForbidden();
    }

    public function test_lists_support_events_only(): void {
        $admin = User::factory()->admin()->create();

        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.access.granted',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['by' => 'platform'],
        ]);
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.reportGenerated',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['sha256' => 'abc'],
        ]);
        // Noise: anderes Event darf nicht auftauchen.
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'tenant.export.csv',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['rows' => 5],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.support.access-audit.index'))
            ->assertOk();

        $response->assertSee('support.access.granted');
        $response->assertSee('support.reportGenerated');
        $response->assertDontSee('tenant.export.csv');
    }

    public function test_event_filter_restricts_results(): void {
        $admin = User::factory()->admin()->create();

        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.access.granted',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => [],
        ]);
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.reportGenerated',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => [],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.support.access-audit.index', ['event' => 'support.reportGenerated']))
            ->assertOk();

        $entries = $response->viewData('entries');
        $this->assertSame(1, $entries->total());
        $this->assertSame('support.reportGenerated', $entries->items()[0]->event);
    }

    public function test_does_not_leak_events_from_other_organization(): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        AuditLog::query()->create([
            'organization_id' => $other->organization_id,
            'user_id' => $other->id,
            'event' => 'support.access.granted',
            'auditable_type' => User::class,
            'auditable_id' => $other->id,
            'changes' => ['secret' => 'do-not-leak'],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.support.access-audit.index'))
            ->assertOk();

        $entries = $response->viewData('entries');
        $this->assertSame(0, $entries->total());
    }
}
