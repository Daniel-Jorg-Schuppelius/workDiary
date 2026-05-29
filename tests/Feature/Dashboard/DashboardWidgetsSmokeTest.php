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

        // KPIs, Finance, Schichten, Notdienste, Team & Onboarding sind fest ins
        // Tab-Dashboard gewandert und nicht mehr als konfigurierbare Widgets registriert.
        // Der Widget-Loop bedient nur noch nicht-überlappende Widgets (Lesezeichen).
        $this->assertContains('bookmarks', $rendered);
        $this->assertNotContains('personal-kpis', $rendered);
        $this->assertNotContains('finance', $rendered);
    }
}
