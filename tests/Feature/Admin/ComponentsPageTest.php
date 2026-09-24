<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComponentsPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\Platform\User;
use App\Services\Release\SbomGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Geschützte Komponenten- und Versionsübersicht (Feature 044):
 * nur Admin (metrics.view, analog admin/metrics), zeigt Versionen,
 * erzeugt die SBOM synchron und liefert den Gate-geprüften Download.
 */
class ComponentsPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_admin_sees_versions_and_sbom_hint_without_sbom(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.components.index'))
            ->assertOk()
            ->assertSee(PHP_VERSION)
            ->assertSee(\Illuminate\Foundation\Application::VERSION)
            ->assertSee((string) config('app.version'))
            ->assertSee('php artisan sbom:generate');
    }

    /** UI-Fuzz 2026-09-21: Org-Admins sahen Update-Import, SBOM- und Manifest-Erzeugung — die Aktionen endeten in 403. */
    public function test_update_actions_are_offered_to_the_platform_operator_only(): void {
        Storage::fake('local');
        $operatorOnly = [route('admin.components.updates.import'), route('admin.components.sbom.generate'), route('admin.components.manifest.generate')];

        $orgAdmin = $this->actingAs(User::factory()->admin()->create())->get(route('admin.components.index'))->assertOk();
        $operator = $this->actingAs(User::factory()->platformAdmin()->create())->get(route('admin.components.index'))->assertOk();
        foreach ($operatorOnly as $action) {
            $orgAdmin->assertDontSee($action, false);
            $operator->assertSee($action, false);
        }
    }

    public function test_non_admin_cannot_access_components_page(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.components.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.components.sbom.generate'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.components.sbom.download'))->assertForbidden();
    }

    public function test_generate_button_creates_sbom_and_download_works(): void {
        Storage::fake('local');
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.components.sbom.generate'))
            ->assertRedirect(route('admin.components.index'))
            ->assertSessionHas('success');

        $this->assertTrue(Storage::disk('local')->exists('sbom/' . SbomGenerator::latestAlias()));

        // Kennzahlen erscheinen auf der Seite (Komponenten gesamt).
        $this->actingAs($admin)
            ->get(route('admin.components.index'))
            ->assertOk()
            ->assertSee(__('isms.components.field.component_count'))
            ->assertSee(__('isms.components.action.download'));

        $this->actingAs($admin)
            ->get(route('admin.components.sbom.download'))
            ->assertOk()
            ->assertDownload(SbomGenerator::latestAlias());
    }

    public function test_download_without_sbom_returns_not_found(): void {
        Storage::fake('local');
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.components.sbom.download'))
            ->assertNotFound();
    }

    public function test_page_surfaces_system_health_section(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.components.index'))
            ->assertOk()
            ->assertSee(__('isms.components.health.title'))
            ->assertSee(__('isms.components.health.run_after_update'))
            // UI-Crawl 2026-09-19: callSilently() ohne Konsolen-Anwendung → „find() on null".
            ->assertDontSee('on null')
            ->assertSee('Keine ausstehenden Migrationen');
    }

    public function test_admin_generates_and_downloads_release_manifest(): void {
        Storage::fake('local');
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.components.manifest.generate'))
            ->assertRedirect(route('admin.components.index'))
            ->assertSessionHas('success');

        $this->assertTrue(Storage::disk('local')->exists(\App\Services\Release\ReleaseManifestService::STORAGE_PATH));

        // Manifest-Kennzahlen erscheinen auf der Seite.
        $this->actingAs($admin)
            ->get(route('admin.components.index'))
            ->assertOk()
            ->assertSee(__('isms.components.manifest.title'))
            ->assertSee(__('isms.components.manifest.artifacts'));

        $this->actingAs($admin)
            ->get(route('admin.components.manifest.download'))
            ->assertOk()
            ->assertDownload('release.json');
    }

    /**
     * Entschieden 2026-09-13 (Sicherheitsaudit): Die Leseansicht bleibt fuer das
     * ISMS der Organisation offen — Stueckliste und Release-Manifest beschreiben
     * dagegen die INSTALLATION samt Abhaengigkeitsversionen und damit ihre
     * Angriffsflaeche. Erzeugen und Herunterladen ist Betreibersache.
     */
    public function test_org_admin_may_read_but_not_generate_installation_artifacts(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.components.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.components.sbom.generate'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.components.sbom.download'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.components.manifest.generate'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.components.manifest.download'))->assertForbidden();
    }

    public function test_non_admin_cannot_generate_or_download_manifest(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('admin.components.manifest.generate'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.components.manifest.download'))->assertForbidden();
    }
}
