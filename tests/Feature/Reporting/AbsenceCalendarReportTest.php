<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsenceCalendarReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{SickLeave, User, Vacation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Urlaubsplan-Jahresübersicht + Fehlzeitenkarte (MVP-520): Sichtbarkeit,
 * Datenschutz-Filter (Fehlgründe neutral), Fehlzeitenkarte, CSV-Export.
 */
class AbsenceCalendarReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $employee;

    private User $colleague;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->employee = $this->orgUser(['name' => 'Erika Beispiel']);
        $this->colleague = $this->orgUser(['name' => 'Kai Kollege']);

        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-17',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
        SickLeave::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->colleague->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'kind' => \App\Enums\Sickness\SickLeaveKind::cases()[0]->value,
        ]);
    }

    public function test_admin_sees_all_rows_with_reasons(): void {
        $this->actingAs($this->admin)
            ->get(route('reports.absence-calendar', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Erika Beispiel')
            ->assertSee('Kai Kollege')
            ->assertSee(__('Krank'));
    }

    public function test_admin_can_anonymize_reasons(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.absence-calendar', ['year' => 2026, 'anon' => 1]))
            ->assertOk();

        // Balken-Tooltips tragen den neutralen Text statt des Fehlgrundes.
        $response->assertDontSee(__('Krank') . ': 2026-03-02');
    }

    public function test_employee_sees_only_own_row(): void {
        $this->actingAs($this->employee)
            ->get(route('reports.absence-calendar', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Erika Beispiel')
            ->assertDontSee('Kai Kollege');
    }

    public function test_employee_cannot_open_foreign_card(): void {
        $this->actingAs($this->employee)
            ->get(route('reports.absence-calendar', [
                'year' => 2026,
                'user' => \App\Support\Sqid::encode(User::class, (int) $this->colleague->id),
            ]))
            ->assertForbidden();
    }

    public function test_card_shows_balance_and_stats(): void {
        $this->actingAs($this->admin)
            ->get(route('reports.absence-calendar', [
                'year' => 2026,
                'user' => \App\Support\Sqid::encode(User::class, (int) $this->employee->id),
            ]))
            ->assertOk()
            ->assertSee(__('Fehlzeitenkarte'))
            ->assertSee(__('Statistik je Fehlgrund'));
    }

    public function test_csv_export_lists_periods(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.absence-calendar', ['year' => 2026, 'export' => 'csv']))
            ->assertOk();

        $csv = (string) $response->getContent();
        $this->assertStringContainsString('Erika Beispiel', $csv);
        $this->assertStringContainsString('2026-07-06', $csv);
    }
}
