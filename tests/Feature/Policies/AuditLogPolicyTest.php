<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLogPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Policies;

use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Audit-Logs (GoBD, revisionssicher) liest, wer `audit-log.view` hat —
 * laut Rollenmatrix Geschäftsführung, Buchhaltung und Support (lk-06) —,
 * und der Admin über den {@see \App\Policies\Concerns\HasAdminBypass}-
 * `before()`-Hook (Gate, damit before() greift).
 */
final class AuditLogPolicyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_regular_user_cannot_view_audit_logs(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue(Gate::forUser($user)->denies('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($user)->denies('view', new AuditLog));
    }

    public function test_audit_log_view_grants_reading_without_admin(): void {
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->assertTrue(Gate::forUser($accountant)->allows('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($lead)->denies('viewAny', AuditLog::class));
    }

    public function test_only_admin_may_view_audit_logs_via_bypass(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', new AuditLog));
    }
}
