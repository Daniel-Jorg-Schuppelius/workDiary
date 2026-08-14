<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoreObjectsReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\{Expense, ScheduledShift, SickLeave, User, Vacation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollaudit 2026-07 (M3): REST-API-Kernobjekte Abwesenheiten, Spesen,
 * Rechnungen und Schichtplan (Feature 008 MVP) — read-first, Scopes erzwungen,
 * eigene-Daten-Sichtbarkeit ohne viewAny.
 */
final class CoreObjectsReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_ability_scope_is_enforced(): void {
        Sanctum::actingAs($this->worker, ['diary:read']);

        $this->getJson(route('api.absences.index'))->assertForbidden();
        $this->getJson(route('api.expenses.index'))->assertForbidden();
        $this->getJson(route('api.invoices.index'))->assertForbidden();
        $this->getJson(route('api.scheduled-shifts.index'))->assertForbidden();
    }

    public function test_absences_list_own_vacation_and_sick_leave(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $makeVacation = fn(User $user) => Vacation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'status' => \App\Enums\Vacation\VacationStatus::Pending,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'type' => 'vacation',
        ]);
        $makeVacation($this->worker);
        $makeVacation($other);
        SickLeave::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->worker->id]);

        Sanctum::actingAs($this->worker, ['absences:read']);
        $response = $this->getJson(route('api.absences.index'))->assertOk();

        $data = collect($response->json('data'));
        $this->assertCount(2, $data); // eigener Urlaub + eigene Krankmeldung, NICHT der fremde Urlaub
        $this->assertSame(['sick', 'vacation'], $data->pluck('kind')->sort()->values()->all());
    }

    public function test_expenses_restricted_to_own_without_view_any(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->worker->id, 'vendor' => 'Eigen GmbH']);
        $foreign = Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $other->id, 'vendor' => 'Fremd AG']);

        Sanctum::actingAs($this->worker, ['expenses:read']);
        $response = $this->getJson(route('api.expenses.index'))->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Eigen GmbH', $response->json('data.0.vendor'));

        $this->getJson(route('api.expenses.show', $foreign))->assertForbidden();
    }

    public function test_invoices_require_billing_role(): void {
        Sanctum::actingAs($this->worker, ['invoices:read']);
        $this->getJson(route('api.invoices.index'))->assertForbidden();

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($admin, ['invoices:read']);
        $this->getJson(route('api.invoices.index'))->assertOk();
    }

    public function test_scheduled_shifts_list_own(): void {
        ScheduledShift::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->worker->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ScheduledShift::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $other->id]);

        Sanctum::actingAs($this->worker, ['scheduled-shifts:read']);
        $response = $this->getJson(route('api.scheduled-shifts.index'))->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
