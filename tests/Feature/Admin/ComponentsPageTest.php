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

use App\Models\User;
use App\Services\Isms\SbomGenerator;
use Database\Seeders\PermissionsSeeder;
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
        $this->seed(PermissionsSeeder::class);
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

    public function test_non_admin_cannot_access_components_page(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.components.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.components.sbom.generate'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.components.sbom.download'))->assertForbidden();
    }

    public function test_generate_button_creates_sbom_and_download_works(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

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
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.components.sbom.download'))
            ->assertNotFound();
    }
}
