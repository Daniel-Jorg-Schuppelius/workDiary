<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardWidgetsSmokeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Dashboard;

use App\Dashboard\WidgetRegistry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DashboardWidgetsSmokeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    #[Test]
    public function every_registered_widget_renders_for_empty_admin_user(): void {
        $user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        /** @var WidgetRegistry $registry */
        $registry = app(WidgetRegistry::class);

        $rendered = [];
        foreach ($registry->availableFor($user) as $widget) {
            $result = $widget->render($user);
            $html = $result instanceof View ? $result->render() : (string) $result;
            $this->assertNotSame('', trim($html), 'Widget ' . $widget->key() . ' rendered empty');
            $rendered[] = $widget->key();
        }

        // Seit dem Kachel-Umbau (2026-08) gibt es keine fest verdrahteten
        // Dashboard-Blöcke mehr: KPIs, Schichten, Finanzen und Team-Aktivität
        // sind Kacheln und damit abwählbar.
        $this->assertContains('bookmarks', $rendered);
        $this->assertContains('personal-kpis', $rendered);
        $this->assertContains('finance', $rendered);
        $this->assertContains('attendance-clock', $rendered);
        $this->assertContains('team-kpis', $rendered);
    }

    /**
     * Jede registrierte Kachel muss rendern — auch die, die für den
     * Testnutzer gar nicht sichtbar wären (Modul aus, Recht fehlt). Der Test
     * geht deshalb bewusst über die vollständige Registry und ruft render()
     * direkt: er prüft Abfragen und Views, nicht die Sichtbarkeit.
     */
    #[Test]
    public function every_widget_in_the_registry_renders_without_data(): void {
        $user = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);

        /** @var WidgetRegistry $registry */
        $registry = app(WidgetRegistry::class);
        $this->assertGreaterThan(20, $registry->all()->count(), 'Registry unerwartet klein');

        foreach ($registry->all() as $widget) {
            $result = $widget->render($user);
            $html = $result instanceof View ? $result->render() : (string) $result;
            $this->assertNotSame('', trim($html), 'Widget ' . $widget->key() . ' rendered empty');
        }
    }

    /** Kachel-Schlüssel müssen eindeutig sein — sonst überschreibt die Registry still. */
    #[Test]
    public function widget_keys_are_unique(): void {
        /** @var WidgetRegistry $registry */
        $registry = app(WidgetRegistry::class);

        $keys = $registry->all()->map(fn ($w) => $w->key())->all();
        $this->assertSame(array_values(array_unique($keys)), array_values($keys));
    }
}
