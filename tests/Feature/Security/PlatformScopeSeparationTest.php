<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlatformScopeSeparationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{MaintenanceWindow, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23, S-02: **Org-Admin ist nicht Plattform-Betreiber.**
 *
 * Die org-lokale `admin`-Rolle bekam bis dahin jede Permission — also auch
 * `platform.*`. Der Admin eines beliebigen Mandanten konnte damit
 * installationsweite Zustände setzen: ein System-Wartungsfenster sperrt ALLE
 * Mandanten, `updates.feed_url` steuert den Update-Check des Betreibers, der
 * Supportbericht enthält den Log-Auszug aller Mandanten. Die mit
 * `is_platform_admin` eingeführte Trennung war damit aufgehoben.
 */
class PlatformScopeSeparationTest extends TestCase {
    use RefreshDatabase;

    /** Seiten, die die ganze Installation zeigen oder steuern. */
    private const PLATFORM_PAGES = [
        'admin.scheduler.index',
        'admin.metrics.index',
        'admin.backup.status',
        'admin.diagnostics.index',
    ];

    private int $orgAdminOrganizationId = 0;

    private function orgAdmin(): User {
        $admin = User::factory()->admin()->create();
        $this->orgAdminOrganizationId = (int) $admin->organization_id;

        return $admin;
    }

    public function test_betreiber_erreicht_die_betriebsseiten(): void {
        // Gegenprobe zuerst: die Schranke darf den Betreiber nicht aussperren.
        $operator = User::factory()->platformAdmin()->create();

        foreach (self::PLATFORM_PAGES as $route) {
            $this->actingAs($operator)->get(route($route))->assertSuccessful();
        }
    }

    public function test_org_admin_kommt_nicht_an_die_betriebsseiten(): void {
        // **Nicht** über einen Rechteentzug: `platform.*` beschreibt in diesem
        // Code keinen Geltungsbereich (die Fehlermelde-Inbox und das
        // Aufgabencenter tragen dasselbe Präfix und sind org-gescopt). Die
        // Grenze sitzt an der Aktion.
        $admin = $this->orgAdmin();

        foreach (self::PLATFORM_PAGES as $route) {
            $this->actingAs($admin)->get(route($route))->assertForbidden();
        }
    }

    public function test_org_admin_sieht_nur_eigene_wartungsfenster(): void {
        $fremde = Organization::factory()->create();

        MaintenanceWindow::query()->create([
            'scope' => MaintenanceWindow::SCOPE_SYSTEM,
            'organization_id' => null,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => MaintenanceWindow::STATUS_PLANNED,
            'message' => 'System-Fenster',
        ]);
        MaintenanceWindow::query()->create([
            'scope' => MaintenanceWindow::SCOPE_ORGANIZATION,
            'organization_id' => $fremde->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'status' => MaintenanceWindow::STATUS_PLANNED,
            'message' => 'Fremdes Fenster',
        ]);

        $response = $this->actingAs($this->orgAdmin())->get(route('admin.maintenance-windows.index'));

        $response->assertOk();

        // Geprüft wird die Liste, nicht die Seite: ein angekündigtes
        // System-Fenster erscheint zu Recht als Hinweisbanner im Layout —
        // es betrifft den Nutzer ja. Nicht erscheinen darf es in der
        // Verwaltungsliste, aus der heraus Fenster gesteuert werden.
        $windows = $response->viewData('windows');

        foreach ($windows as $window) {
            $this->assertSame(MaintenanceWindow::SCOPE_ORGANIZATION, $window->scope, 'Fremd-Scope in der Liste');
            $this->assertSame($this->orgAdminOrganizationId, (int) $window->organization_id, 'Fremde Organisation in der Liste');
        }
    }

    public function test_org_eigene_platform_praefix_funktionen_bleiben_offen(): void {
        // Der pauschale Entzug hätte genau das kaputtgemacht: alle drei sind
        // dokumentierte Org-Admin-Funktionen, die nur zufällig ein
        // `platform.`-Präfix tragen.
        $admin = $this->orgAdmin();

        $this->assertTrue($admin->hasEffectivePermission('platform.problemReports.manage'));
        $this->assertTrue($admin->hasEffectivePermission('platform.operations.view'));

        // Supportbericht: supportbericht.md §6 weist ihn ausdrücklich
        // „Plattform-/Org-Admin" zu.
        $this->actingAs($admin)->get(route('admin.support.report.index'))->assertOk();
    }

    public function test_proben_im_supportbericht_bleiben_dem_betreiber(): void {
        // §6 derselben Doku: `exportWithSamples` ist Plattform-Admin. Die
        // Permission allein trug das nicht — die org-lokale admin-Rolle hält
        // sie ebenfalls.
        $this->actingAs($this->orgAdmin())
            ->post(route('admin.support.report.generate'), ['include_samples' => '1'])
            ->assertSessionHasErrors('include_samples');
    }

    public function test_org_admin_sieht_seine_eigenen_einstellungen(): void {
        // Die Einstellungsseite führt org- UND systemweite Schlüssel. Ein
        // Org-Admin darf seine eigenen sehen — nur der System-Scope ist dicht.
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['scope' => 'organization']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['scope' => 'system']))
            ->assertForbidden();
    }

    public function test_org_admin_kann_kein_system_wartungsfenster_planen(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->post(route('admin.maintenance-windows.store'), [
                'scope' => MaintenanceWindow::SCOPE_SYSTEM,
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
                'read_only' => '1',
                'block_ingest' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('maintenance_windows', ['scope' => MaintenanceWindow::SCOPE_SYSTEM]);
    }

    public function test_fremdes_wartungsfenster_bleibt_unantastbar(): void {
        $operator = User::factory()->platformAdmin()->create();
        $fremde = Organization::factory()->create();

        $window = MaintenanceWindow::query()->create([
            'scope' => MaintenanceWindow::SCOPE_ORGANIZATION,
            'organization_id' => $fremde->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => MaintenanceWindow::STATUS_PLANNED,
        ]);

        // Der Betreiber darf; ein Org-Admin einer anderen Organisation nicht —
        // MaintenanceWindow trägt keinen OrganizationScope, die Sqid allein
        // wäre sonst der Schlüssel zu jedem fremden Fenster.
        $this->assertTrue($operator->isGlobalAdmin());

        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->post(route('admin.maintenance-windows.transition', ['maintenanceWindow' => $window->sqid, 'action' => 'announce']))
            ->assertForbidden();
    }

    // ── S-14 · Der Betreiber ist kein gewöhnliches Mitglied ─────────────

    public function test_org_admin_kann_den_betreiber_nicht_verwalten(): void {
        $admin = $this->orgAdmin();

        // In einer On-Prem-Installation sitzt der Betreiber in derselben
        // (einzigen) Organisation — genau das war der Angriffspfad.
        $betreiber = User::factory()->platformAdmin()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)->get(route('org.members.edit', $betreiber->sqid))->assertForbidden();

        $this->actingAs($admin)->put(route('org.members.update', $betreiber->sqid), [
            'name' => 'Übernommen',
            'email' => 'uebernommen@example.test',
            'new_password' => 'ein-neues-geheimnis-123',
            'role' => 'user',
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('org.members.destroy', $betreiber->sqid))->assertForbidden();

        $betreiber->refresh();
        $this->assertTrue($betreiber->isGlobalAdmin());
        $this->assertNotSame('uebernommen@example.test', $betreiber->email);
    }

    public function test_betreiber_verwaltet_betreiber(): void {
        // Gegenprobe: unter Betreibern bleibt die Verwaltung möglich.
        $einer = User::factory()->platformAdmin()->create();
        $anderer = User::factory()->platformAdmin()->create(['organization_id' => $einer->organization_id]);

        $this->actingAs($einer)->get(route('org.members.edit', $anderer->sqid))->assertOk();
    }

}
