<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoFreshOrgUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Models\{AuditLog, Customer, DiaryEntry, Organization, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * freshDemoOrg-UI (MVP-349, demo-mandant.md §2): Plattform-Admin erzeugt über
 * die Mandantenverwaltung eine neue, isolierte Demo-Organisation aus einer
 * Musterbranche — inkl. `demo.orgCreated`-Audit und optionaler Membership-
 * Zuweisung. NUR Plattform-Admin (org-lokale Admins und Normalnutzer: 403).
 */
final class DemoFreshOrgUiTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_dialog_renders_for_platform_admin_with_industries(): void {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->get(route('admin.demo.fresh-org.create'))
            ->assertOk()
            ->assertSee(__('Demo-Organisation anlegen'))
            ->assertSee('it-service')
            ->assertSee('wartung-service')
            ->assertSee(__('Keine Zuweisung'));
    }

    public function test_dialog_and_store_are_forbidden_for_org_admin_and_regular_user(): void {
        $orgAdmin = User::factory()->admin()->create();
        $regular = User::factory()->user()->create();

        $this->actingAs($orgAdmin)->get(route('admin.demo.fresh-org.create'))->assertForbidden();
        $this->actingAs($orgAdmin)->post(route('admin.demo.fresh-org.store'), ['industry' => 'it-service'])->assertForbidden();
        $this->actingAs($regular)->get(route('admin.demo.fresh-org.create'))->assertForbidden();
        $this->actingAs($regular)->post(route('admin.demo.fresh-org.store'), ['industry' => 'it-service'])->assertForbidden();

        $this->assertSame(0, Organization::query()->where('is_demo', true)->count());
    }

    public function test_store_creates_isolated_demo_org_with_blueprint_contents_and_audit(): void {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $ownOrgId = (int) $platformAdmin->organization_id;

        $this->actingAs($platformAdmin)
            ->from(route('admin.organizations.index'))
            ->post(route('admin.demo.fresh-org.store'), ['industry' => 'wartung-service'])
            ->assertRedirect(route('admin.organizations.index'));

        $demo = Organization::query()->where('is_demo', true)->firstOrFail();
        $this->assertNotSame($ownOrgId, (int) $demo->id);
        $this->assertStringContainsString('Demo', (string) $demo->name);

        // Blueprint-Inhalte (Zähl-Asserts wie DemoSeederServiceTest). Hinweis:
        // die Org-Anlage erzeugt zusätzlich Default-Projekte, daher bei
        // Projekten "mindestens" (analog DemoSeederServiceTest).
        $this->assertSame(3, Customer::query()->withoutGlobalScopes()->where('organization_id', $demo->id)->count());
        $this->assertGreaterThanOrEqual(5, Project::query()->withoutGlobalScopes()->where('organization_id', $demo->id)->count());
        $this->assertSame(26, DiaryEntry::query()->withoutGlobalScopes()->where('organization_id', $demo->id)->count());
        $this->assertSame(6, User::query()
            ->where('organization_id', $demo->id)
            ->where('email', 'like', 'demo+%@workdiary.test')
            ->count());

        // Audit demo.orgCreated (§8): Branche, Org-ID, Ersteller. AuditLog ist
        // org-gescopt — im Testkontext klebt der Scope an der Ursprungs-Org.
        $log = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $demo->id)
            ->where('event', 'demo.orgCreated')
            ->firstOrFail();
        $this->assertSame($platformAdmin->id, (int) $log->user_id);
        $changes = (array) $log->getAttribute('changes');
        $this->assertSame('wartung-service', $changes['industry'] ?? null);
        $this->assertSame((int) $demo->id, (int) ($changes['organization_id'] ?? 0));
        $this->assertSame($platformAdmin->id, (int) ($changes['created_by'] ?? 0));

        // demo.seeded mit Counts läuft weiterhin mit (etablierter Schreibweg).
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $demo->id,
            'event' => 'demo.seeded',
        ]);

        // Ohne Membership-Wunsch bleibt der Ausführende in seiner Org.
        $this->assertSame($ownOrgId, (int) $platformAdmin->refresh()->organization_id);
    }

    public function test_store_assigns_selected_platform_admin_as_member(): void {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->post(route('admin.demo.fresh-org.store'), [
                'industry' => 'it-service',
                'member' => $platformAdmin->sqid,
            ])
            ->assertRedirect(route('admin.organizations.index'));

        $demo = Organization::query()->where('is_demo', true)->firstOrFail();
        $this->assertSame((int) $demo->id, (int) $platformAdmin->refresh()->organization_id);

        $log = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $demo->id)
            ->where('event', 'demo.orgCreated')
            ->firstOrFail();
        $this->assertSame($platformAdmin->id, (int) data_get($log->getAttribute('changes'), 'member_user_id'));
    }

    public function test_store_rejects_member_who_is_not_platform_admin(): void {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $regular = User::factory()->user()->create(['organization_id' => $platformAdmin->organization_id]);

        $this->actingAs($platformAdmin)
            ->from(route('admin.organizations.index'))
            ->post(route('admin.demo.fresh-org.store'), [
                'industry' => 'it-service',
                'member' => $regular->sqid,
            ])
            ->assertSessionHasErrors('member');

        // Kein Mandant entstanden, keine Membership verschoben.
        $this->assertSame(0, Organization::query()->where('is_demo', true)->count());
        $this->assertSame((int) $platformAdmin->organization_id, (int) $regular->refresh()->organization_id);
    }
}
