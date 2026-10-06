<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ChangeStatus;
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\ServiceTicket\{Change, Problem, ServiceTicket};
use App\Services\ServiceTicket\{ChangeService, HelpdeskMetricsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): der Change führt seinen
 * Status als Enum. Ein Vergleich gegen die frühere Zeichenkette ist danach
 * still falsch — Abschluss, Umsetzung und Asset-Verknüpfung fehlten auf der
 * Seite, die Label-Tabelle bräche mit einem Typfehler.
 */
final class ChangeStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->manager = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function change(ChangeStatus $status, array $attributes = []): Change {
        return Change::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Firewall ' . $status->value,
            'rollback_plan' => 'Alte Firmware zurückflashen',
            'status' => $status,
        ], $attributes));
    }

    private function page(string $url): string {
        return (string) $this->actingAs($this->manager)->get($url)->assertOk()->getContent();
    }

    /** Eigene Meldung statt `assertSee`: Dessen Fehlertext trägt die ganze Seite. */
    private function assertPageHas(string $needle, string $html): void {
        $this->assertTrue(str_contains($html, $needle), "Auf der Seite fehlt: {$needle}");
    }

    private function assertPageLacks(string $needle, string $html): void {
        $this->assertFalse(str_contains($html, $needle), "Auf der Seite steht unerwartet: {$needle}");
    }

    public function test_change_page_offers_the_actions_that_fit_the_status(): void {
        Asset::factory()->create(['organization_id' => $this->organization->id]);

        foreach (ChangeStatus::cases() as $status) {
            $change = $this->change($status);
            $html = $this->page(route('servicedesk.changes.show', $change));

            $complete = 'href="' . route('servicedesk.changes.complete-form', $change) . '"';
            $implement = 'action="' . route('servicedesk.changes.implement', $change) . '"';
            $assets = 'action="' . route('servicedesk.changes.assets.store', $change) . '"';

            in_array($status, [ChangeStatus::Approved, ChangeStatus::Implementing], true)
                ? $this->assertPageHas($complete, $html)
                : $this->assertPageLacks($complete, $html);
            $status === ChangeStatus::Approved
                ? $this->assertPageHas($implement, $html)
                : $this->assertPageLacks($implement, $html);
            in_array($status, [ChangeStatus::Done, ChangeStatus::Cancelled], true)
                ? $this->assertPageLacks($assets, $html)
                : $this->assertPageHas($assets, $html);
            $this->assertPageHas(e($status->label()), $html);
        }
    }

    public function test_lists_name_and_filter_the_status(): void {
        $problem = Problem::query()->create(['organization_id' => $this->organization->id, 'title' => 'Wiederkehrender Ausfall']);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Firewall down']);
        $implementing = $this->change(ChangeStatus::Implementing, ['problem_id' => $problem->id]);
        $implementing->tickets()->attach($ticket->id);
        $this->change(ChangeStatus::Done, ['outcome' => 'successful']);

        $html = $this->page(route('servicedesk.changes.index'));
        $this->assertPageHas('Firewall implementing', $html);
        $this->assertPageHas('Firewall done', $html);
        foreach (ChangeStatus::cases() as $status) {
            $this->assertSame(1, preg_match('/<option value="' . $status->value . '"[^>]*>\s*' . preg_quote(e($status->label()), '/') . '\s*</u', $html), "Filter ohne {$status->value}.");
        }

        $filtered = $this->page(route('servicedesk.changes.index', ['status' => 'done']));
        $this->assertPageHas('Firewall done', $filtered);
        $this->assertPageLacks('Firewall implementing', $filtered);
        // Unbekannter Filterwert: wie bisher ungefiltert.
        $this->assertPageHas('Firewall implementing', $this->page(route('servicedesk.changes.index', ['status' => 'unbekannt'])));

        $label = e(ChangeStatus::Implementing->label());
        $this->assertSame(1, preg_match('/Firewall implementing.*?' . preg_quote($label, '/') . '/su', $this->page(route('servicedesk.problems.show', $problem))), 'Problemseite ohne Status des Changes.');
        $this->assertSame(1, preg_match('/Firewall implementing.*?' . preg_quote($label, '/') . '/su', $this->page(route('service-tickets.show', $ticket))), 'Ticketseite ohne Status des Changes.');
    }

    /** Umsetzung nur aus „genehmigt“, Abschluss aus „genehmigt“ und „in Umsetzung“ — wie vor der Übergangstabelle. */
    public function test_service_guards_follow_the_transition_table(): void {
        $service = app(ChangeService::class);

        foreach (ChangeStatus::cases() as $status) {
            foreach ([
                'implement' => [fn (Change $change) => $service->implement($change, $this->manager), $status === ChangeStatus::Approved, ChangeStatus::Implementing],
                'complete' => [fn (Change $change) => $service->complete($change, $this->manager, 'successful'), in_array($status, [ChangeStatus::Approved, ChangeStatus::Implementing], true), ChangeStatus::Done],
            ] as $action => [$call, $allowed, $target]) {
                $change = $this->change($status);
                try {
                    $call($change);
                    $passed = true;
                } catch (\RuntimeException) {
                    $passed = false;
                }
                $this->assertSame($allowed, $passed, "{$action} aus {$status->value}");
                $this->assertSame($allowed ? $target : $status, $change->fresh()->status);
            }
        }
    }

    public function test_report_counts_completed_changes_only(): void {
        $this->change(ChangeStatus::Done, ['outcome' => 'successful']);
        $this->change(ChangeStatus::Done, ['outcome' => 'rolled_back']);
        $this->change(ChangeStatus::Cancelled, ['outcome' => 'cancelled']);
        $this->change(ChangeStatus::Implementing);

        $this->assertSame(
            ['rolled_back' => 1, 'successful' => 1],
            app(HelpdeskMetricsService::class)->changeOutcomes(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')),
        );
    }

    public function test_new_change_starts_as_draft_until_the_service_decides(): void {
        $this->assertSame(ChangeStatus::Draft, (new Change)->status);
        $this->assertDatabaseHas('changes', ['id' => $this->change(ChangeStatus::PendingApproval)->id, 'status' => 'pending_approval']);
    }
}
