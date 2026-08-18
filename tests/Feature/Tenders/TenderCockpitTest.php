<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderCockpitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Tenders;

use App\Models\Applications\ApplicationOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vergabe-Cockpit (MVP-631): Fristensicht, Trefferquote, Verlustgründe.
 *
 * Im Vergabegeschäft entscheiden Fristen — deshalb prüft der Test vor allem,
 * dass die Fristensichten **unabhängig vom Zeitraumfilter** den offenen
 * Bestand zeigen.
 */
final class TenderCockpitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function opportunity(array $attributes = []): ApplicationOpportunity {
        return ApplicationOpportunity::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Vorgang',
            'kind' => 'tender',
            'status' => 'in_progress',
            'estimated_value' => '10000',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_cockpit_renders_pipeline_and_win_rate(): void {
        $this->opportunity(['title' => 'Gewonnen', 'status' => 'won', 'estimated_value' => '30000']);
        $this->opportunity(['title' => 'Verloren', 'status' => 'lost', 'loss_reason' => 'Preis zu hoch']);

        $this->actingAs($this->admin)
            ->get(route('tenders.cockpit'))
            ->assertOk()
            ->assertSee('Vergabe-Cockpit')
            // Eine von zwei Entscheidungen gewonnen.
            ->assertSee('50 %')
            ->assertSee('Preis zu hoch');
    }

    /**
     * Eine überfällige Abgabe verschwindet nicht, weil der Bericht auf einen
     * anderen Zeitraum eingestellt ist — sonst würde die Fristenlast genau
     * dann unsichtbar, wenn sie am meisten zählt.
     */
    public function test_deadline_view_ignores_the_period_filter(): void {
        $this->opportunity([
            'title' => 'Überfällig',
            'status' => 'in_progress',
            'submission_deadline' => now()->subDays(3)->toDateString(),
            'responsible_user_id' => $this->admin->id,
        ]);

        // Zeitraum liegt vor der Anlage des Vorgangs.
        $response = $this->actingAs($this->admin)->get(route('tenders.cockpit', [
            'from' => now()->subYear()->toDateString(),
            'to' => now()->subYear()->addDay()->toDateString(),
        ]));

        $response->assertOk()->assertSee('Überfällige Abgaben');
        $deadlines = $response->viewData('deadlines');
        $this->assertSame(1, $deadlines['overdue']['count']);
    }

    /** Entschiedene und zurückgezogene Vorgänge tauchen nicht als offene Frist auf. */
    public function test_decided_cases_leave_the_deadline_load(): void {
        $this->opportunity(['status' => 'won', 'submission_deadline' => now()->subDays(2)->toDateString()]);
        $this->opportunity(['status' => 'withdrawn', 'submission_deadline' => now()->subDays(2)->toDateString()]);

        $response = $this->actingAs($this->admin)->get(route('tenders.cockpit'));

        $this->assertSame(0, $response->viewData('deadlines')['overdue']['count']);
        $this->assertSame([], $response->viewData('workload'));
    }

    /** Ohne entschiedene Vorgänge gibt es keine Quote — 0 % wäre gelogen. */
    public function test_win_rate_is_empty_without_decisions(): void {
        $this->opportunity(['status' => 'in_progress']);

        $response = $this->actingAs($this->admin)->get(route('tenders.cockpit'));

        $this->assertNull($response->viewData('decision')['win_rate']);
    }

    /** Die Fristenlast sortiert das Dringendste nach oben. */
    public function test_workload_puts_the_most_urgent_first(): void {
        $calm = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Ruhig']);
        $busy = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Unter Druck']);

        // Viele Vorgänge, aber alle mit Luft.
        for ($i = 0; $i < 4; $i++) {
            $this->opportunity(['responsible_user_id' => $calm->id, 'submission_deadline' => now()->addMonths(3)->toDateString()]);
        }
        $this->opportunity(['responsible_user_id' => $busy->id, 'submission_deadline' => now()->subDay()->toDateString()]);

        $workload = $this->actingAs($this->admin)->get(route('tenders.cockpit'))->viewData('workload');

        $this->assertSame('Unter Druck', $workload[0]['name']);
        $this->assertSame(1, $workload[0]['overdue']);
        $this->assertSame(4, $workload[1]['open']);
    }

    public function test_csv_export_carries_every_block(): void {
        $this->opportunity(['status' => 'lost', 'loss_reason' => 'Preis zu hoch']);
        $this->opportunity(['status' => 'in_progress', 'submission_deadline' => now()->addDays(3)->toDateString()]);

        $csv = $this->actingAs($this->admin)->get(route('tenders.cockpit', ['export' => 'csv']));

        $csv->assertOk();
        $body = $csv->getContent();
        $this->assertStringContainsString('Fristfenster', $body);
        $this->assertStringContainsString('Verlustgrund', $body);
        $this->assertStringContainsString('Trefferquote', $body);
    }

    public function test_plain_user_is_denied(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('tenders.cockpit'))->assertForbidden();
    }
}
