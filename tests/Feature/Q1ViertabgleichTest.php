<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Q1ViertabgleichTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Notification\NotificationEvent;
use App\Enums\TimeAccount\{CarryoverPolicy, TimeAccountUnit};
use App\Enums\Vacation\VacationStatus;
use App\Models\{TimeAccount, TimeAccountEntry, User, Vacation};
use App\Notifications\GenericEventNotification;
use App\Services\Compliance\{AttendanceComplianceFinding, AttendancePlausibilityScanService, ComplianceFindingRecorder};
use App\Support\XlsxExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Q1-Viertabgleich (MVP-538–540): Urlaubs-Benachrichtigungen, Selbstsicht
 * „Ungeklärte Fälle", XLSX-Export über das Report-Trait und der
 * Zeitkonten-Periodenvergleich.
 */
class Q1ViertabgleichTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->employee = $this->orgUser();
    }

    // ── MVP-538: Urlaubs-Benachrichtigungen ─────────────────────────────────

    public function test_vacation_request_notifies_lead(): void {
        Notification::fake();
        // Factory-State statt userWithRole: hängt wie die Admin-UI AUCH die
        // globale Rollenzeile an, die die Rollen-Abfrage des Dispatchers matcht.
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->employee)
            ->post(route('vacations.store'), [
                'start_date' => '2026-09-07',
                'end_date' => '2026-09-11',
                'type' => 'vacation',
            ])
            ->assertRedirect(route('duties.index', ['tab' => 'urlaub']));

        Notification::assertSentTo(
            $lead,
            GenericEventNotification::class,
            fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::VacationRequested,
        );
    }

    public function test_vacation_decision_notifies_owner(): void {
        Notification::fake();
        $vacation = Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'type' => 'vacation',
            'status' => VacationStatus::Pending,
        ]);

        $this->actingAs($this->orgAdmin())
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();

        $this->assertSame(VacationStatus::Approved, $vacation->fresh()->status);
        Notification::assertSentTo(
            $this->employee,
            GenericEventNotification::class,
            fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::VacationDecided,
        );
    }

    public function test_vacation_rejection_notifies_owner(): void {
        Notification::fake();
        $vacation = Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'type' => 'vacation',
            'status' => VacationStatus::Pending,
        ]);

        $this->actingAs($this->orgAdmin())
            ->patch(route('vacations.reject', $vacation), ['reject_reason' => 'Betriebsferien kollidieren.'])
            ->assertRedirect();

        Notification::assertSentTo(
            $this->employee,
            GenericEventNotification::class,
            fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::VacationDecided,
        );
    }

    // ── MVP-538: Selbstsicht „Ungeklärte Fälle" ─────────────────────────────

    public function test_new_plausibility_finding_notifies_affected_person(): void {
        Notification::fake();

        $this->recordMissingCheckoutFinding();

        Notification::assertSentTo(
            $this->employee,
            GenericEventNotification::class,
            fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::AttendanceUnclearCase,
        );
    }

    public function test_overtime_page_lists_own_unclear_cases(): void {
        $this->recordMissingCheckoutFinding();

        $this->actingAs($this->employee)
            ->get(route('overtime.index'))
            ->assertOk()
            ->assertSee(__('Ungeklärte Fälle'))
            ->assertSee(__('compliance.report.kind.' . AttendancePlausibilityScanService::KIND_MISSING_CHECKOUT));

        // Fremde Befunde bleiben unsichtbar.
        $other = $this->orgUser();
        $this->actingAs($other)
            ->get(route('overtime.index'))
            ->assertOk()
            ->assertDontSee(__('Ungeklärte Fälle'));
    }

    // ── MVP-539: XLSX über das Report-Trait ─────────────────────────────────

    public function test_time_accounts_report_exports_xlsx(): void {
        $account = $this->accountWithEntries();

        $response = $this->actingAs($this->orgAdmin())
            ->get(route('reports.time-accounts', [
                'account' => \App\Support\Sqid::encode(TimeAccount::class, (int) $account->id),
                'from' => '2026-06-01',
                'to' => '2026-06-30',
                'export' => 'xlsx',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', XlsxExport::MIME);

        // XLSX ist ein ZIP-Container — Magic Bytes "PK".
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    // ── MVP-540: Zeitkonten-Periodenvergleich ───────────────────────────────

    public function test_comparison_report_shows_period_columns(): void {
        $this->accountWithEntries();

        $this->actingAs($this->orgAdmin())
            ->get(route('reports.time-account-comparison', [
                'from' => '2026-06-01',
                'to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertSee($this->employee->name)
            ->assertSee(__('KW :week', ['week' => 23]));
    }

    public function test_comparison_report_csv_contains_period_turnover(): void {
        $account = $this->accountWithEntries();

        $response = $this->actingAs($this->orgAdmin())
            ->get(route('reports.time-account-comparison', [
                'account' => \App\Support\Sqid::encode(TimeAccount::class, (int) $account->id),
                'from' => '2026-06-01',
                'to' => '2026-06-30',
                'export' => 'csv',
            ]))
            ->assertOk();

        $csv = (string) $response->getContent();
        $this->assertStringContainsString('60.00', $csv);   // KW 23
        $this->assertStringContainsString('30.00', $csv);   // KW 24
        $this->assertStringContainsString('90.00', $csv);   // Umsatz/Endstand
    }

    public function test_comparison_report_xlsx_exports_all_accounts_as_sheets(): void {
        $this->accountWithEntries();
        TimeAccount::create([
            'organization_id' => $this->organization->id,
            'code' => 'oncall',
            'name' => 'Rufbereitschafts-Konto',
            'unit' => TimeAccountUnit::Minutes->value,
            'carryover_policy' => CarryoverPolicy::Carry->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->orgAdmin())
            ->get(route('reports.time-account-comparison', [
                'from' => '2026-06-01',
                'to' => '2026-06-30',
                'export' => 'xlsx',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', XlsxExport::MIME);

        $content = (string) $response->getContent();
        $this->assertStringStartsWith('PK', $content);

        // Beide Konten als Arbeitsblätter derselben Mappe.
        $tmp = tempnam(sys_get_temp_dir(), 'wd-xlsx');
        file_put_contents($tmp, $content);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        unlink($tmp);
        $this->assertStringContainsString('Nachtstunden-Konto', $workbook);
        $this->assertStringContainsString('Rufbereitschafts-Konto', $workbook);
    }

    // ── Helfer ──────────────────────────────────────────────────────────────

    private function recordMissingCheckoutFinding(): void {
        app(ComplianceFindingRecorder::class)->record(
            $this->organization,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            [
                (int) $this->employee->id => [new AttendanceComplianceFinding(
                    userId: (int) $this->employee->id,
                    date: '2026-06-10',
                    kind: AttendancePlausibilityScanService::KIND_MISSING_CHECKOUT,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: 60,
                    threshold: 0,
                )],
            ],
            AttendancePlausibilityScanService::CATEGORY,
        );
    }

    /** Konto mit 60 Min. in KW 23 und 30 Min. in KW 24 (Juni 2026). */
    private function accountWithEntries(): TimeAccount {
        $account = TimeAccount::create([
            'organization_id' => $this->organization->id,
            'code' => 'nightshift',
            'name' => 'Nachtstunden-Konto',
            'unit' => TimeAccountUnit::Minutes->value,
            'carryover_policy' => CarryoverPolicy::Carry->value,
            'is_active' => true,
        ]);
        TimeAccountEntry::create([
            'organization_id' => $this->organization->id,
            'time_account_id' => $account->id,
            'user_id' => $this->employee->id,
            'booking_date' => '2026-06-02',
            'quantity' => 60,
        ]);
        TimeAccountEntry::create([
            'organization_id' => $this->organization->id,
            'time_account_id' => $account->id,
            'user_id' => $this->employee->id,
            'booking_date' => '2026-06-10',
            'quantity' => 30,
        ]);

        return $account;
    }
}
