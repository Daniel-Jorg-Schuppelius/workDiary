<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoOrganizationExclusionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Models\{DiaryEntry, Organization, User};
use App\Services\Metrics\OperationsMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo-Organisationen fließen nicht in plattformweite Zahlen und Übersichten
 * ein (MVP-807, Entscheid P12-17; Demo-Konzept Abschnitt 6).
 */
final class DemoOrganizationExclusionTest extends TestCase {
    use RefreshDatabase;

    public function test_platform_wide_metrics_leave_demo_organizations_out(): void {
        $real = Organization::factory()->create();
        $demo = Organization::factory()->create(['is_demo' => true]);
        $this->entries($real, 2);
        $this->entries($demo, 5);
        $metrics = app(OperationsMetricsService::class);

        app()->forgetInstance('currentOrganization');
        $this->assertSame(2, $metrics->collect()['module_counts']['diary_entries']);

        // Innerhalb der Demo-Organisation zählen ihre eigenen Daten weiter.
        app()->instance('currentOrganization', $demo);
        $this->assertSame(5, $metrics->collect()['module_counts']['diary_entries']);
        app()->forgetInstance('currentOrganization');
    }

    public function test_organization_list_hides_demo_organizations_until_shown(): void {
        $admin = User::factory()->platformAdmin()->create();
        Organization::factory()->create(['name' => 'Echtbetrieb GmbH']);
        Organization::factory()->create(['name' => 'Musterfirma Demo', 'is_demo' => true]);

        $hidden = $this->actingAs($admin)->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertSee(__('Demo-Organisationen einblenden (:count)', ['count' => 1]));
        // Nur die Tabelle zählt — der Organisationswechsler im Kopf listet alle.
        $this->assertStringContainsString('Echtbetrieb GmbH', $this->table($hidden->getContent()));
        $this->assertStringNotContainsString('Musterfirma Demo', $this->table($hidden->getContent()));

        $shown = $this->actingAs($admin)->get(route('admin.organizations.index', ['show_demo' => 1]))
            ->assertOk()
            ->assertSee(__('Demo-Organisationen ausblenden'));
        $this->assertStringContainsString('Musterfirma Demo', $this->table($shown->getContent()));
    }

    private function table(string|false $html): string {
        $html = (string) $html;
        $start = strpos($html, '<table');
        $end = strpos($html, '</table>');

        return $start !== false && $end !== false ? substr($html, $start, $end - $start) : '';
    }

    private function entries(Organization $organization, int $count): void {
        $user = User::factory()->create(['organization_id' => $organization->id]);
        DiaryEntry::factory()->count($count)->for($user)->create(['organization_id' => $organization->id]);
    }
}
