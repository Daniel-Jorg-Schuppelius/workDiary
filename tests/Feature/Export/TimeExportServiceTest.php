<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Export;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, MonthClosure, TimeExportEvent, User};
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\{TimeExportException, TimeExportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-019 — ApprovedTimeExporter Pipeline-Tests.
 */
class TimeExportServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TimeExportService $service;
    private MonthClosureService $closureService;
    private int $year = 2024;
    private int $month = 1;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');

        $this->service = app(TimeExportService::class);
        $this->closureService = app(MonthClosureService::class);
    }

    public function test_prepare_fails_when_no_closures_exist(): void {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $this->expectException(TimeExportException::class);
        $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'generic',
            'organization',
            actor: $admin,
        );
    }

    /**
     * Vollaudit 2026-07 (H1): Wer Zeitdaten hat, aber nie eingereicht hat
     * (MonthClosure entsteht nur lazy beim Seitenaufruf), darf nicht still
     * im Org-Export fehlen — Abbruch mit Blockerliste (zeit-export.md §3/§9).
     */
    public function test_prepare_aborts_with_blocker_list_when_user_with_data_has_no_closure(): void {
        $admin = $this->makeAdmin();
        $submitted = $this->makeUser();
        $silent = $this->makeUser();
        $this->seedAttendance($submitted, 8 * 60);
        $this->seedAttendance($silent, 6 * 60);
        $this->approvedClosureFor($submitted, $admin);
        $this->actingAs($admin);

        try {
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
            $this->fail('Export ohne Monatsfreigabe des zweiten Nutzers wurde nicht abgebrochen.');
        } catch (TimeExportException $e) {
            $this->assertSame('missingClosures', $e->reasonCode);
            $this->assertSame(
                [['user_id' => (int) $silent->id, 'status' => 'missing']],
                $e->context['missing'],
            );
        }

        // Nach Einreichung + Genehmigung des zweiten Nutzers läuft prepare durch.
        $this->approvedClosureFor($silent, $admin);
        $this->actingAs($admin);
        $export = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $this->assertSame(TimeExportStatus::Preparing, $export->status);
    }

    public function test_prepare_fails_when_closure_not_approved(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);

        $this->actingAs($user);
        $closure = $this->closureService->getOrCreate($user, $this->year, $this->month);
        $this->closureService->submit($closure, $user);
        // bewusst NICHT approven

        $this->actingAs($admin);

        $this->expectException(TimeExportException::class);
        $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'generic',
            'organization',
            actor: $admin,
        );
    }

    public function test_build_writes_file_hashes_and_locks_closure(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);   // 8 h Tag 15.
        $this->seedAttendance($user, 7 * 60, 16); // 7 h Tag 16. => 15 h gesamt

        $closure = $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'generic',
            'organization',
            actor: $admin,
        );
        $this->assertSame(TimeExportStatus::Preparing, $export->status);

        $built = $this->service->build($export, $admin);

        $this->assertSame(TimeExportStatus::Ready, $built->status);
        $this->assertNotNull($built->payload_hash);
        $this->assertNotNull($built->file_path);
        $this->assertSame(1, $built->rows_count, 'Eine Zeile (work.normal) pro User.');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $localDisk */
        $localDisk = Storage::disk('local');
        $localDisk->assertExists((string) $built->file_path);

        // Totals: 15 h für work.normal
        $totals = $built->totals;
        $this->assertIsArray($totals);
        $this->assertArrayHasKey('work.normal', $totals);
        $this->assertEqualsWithDelta(15.0, (float) $totals['work.normal']['quantity'], 0.001);

        // MonthClosure ist jetzt locked
        $closure->refresh();
        $this->assertSame(MonthClosureStatus::Locked, $closure->status);

        // Hash reproduzierbar
        $expected = hash('sha256', (string) Storage::disk('local')->get((string) $built->file_path));
        $this->assertSame($expected, $built->payload_hash);

        // Audit-Events vorhanden
        $events = TimeExportEvent::query()->where('time_export_id', $built->id)->pluck('event')->all();
        $this->assertContains('export.preparing', $events);
        $this->assertContains('export.ready', $events);
    }

    public function test_re_export_supersedes_old_ready_export(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);
        $closure = $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $first = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $first = $this->service->build($first, $admin);

        // Re-Open + erneut approven, damit prepare durchgeht.
        $closure->refresh();
        $this->closureService->reopen($closure, $admin, 'Korrektur fuer Re-Export Zwecke');
        $closure->refresh();
        $this->actingAs($user);
        $this->closureService->submit($closure, $user);
        $closure->refresh();
        $this->actingAs($admin);
        $this->closureService->approve($closure, $admin);

        $second = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $second = $this->service->build($second, $admin);

        $first->refresh();
        $this->assertSame(TimeExportStatus::Superseded, $first->status);
        $this->assertSame($second->id, $first->superseded_by_id);
        $this->assertSame(TimeExportStatus::Ready, $second->status);
    }

    public function test_mark_delivered_and_reject_transitions(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin),
            $admin,
        );

        $delivered = $this->service->markDelivered($export, $admin, 'an DATEV gesendet');
        $this->assertSame(TimeExportStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertSame('an DATEV gesendet', $delivered->delivery_note);

        $rejected = $this->service->reject($delivered, $admin, 'Lohnbüro meldet Fehler');
        $this->assertSame(TimeExportStatus::Rejected, $rejected->status);
    }

    public function test_datev_lodas_profile_renders_personnel_date_wagetype_hours(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['personnel_number' => '4711'])->save();

        $this->seedAttendance($user, 8 * 60);    // 8 h Tag 15.
        $this->seedAttendance($user, 7 * 60, 16); // 7 h Tag 16. => 15 h gesamt

        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'datev',
            'organization',
            actor: $admin,
        );
        $built = $this->service->build($export, $admin);

        $this->assertSame(TimeExportStatus::Ready, $built->status);
        $this->assertSame('csv', $built->file_format);

        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content), static fn (string $l): bool => $l !== ''));

        // Kopfzeile exakt im LODAS-nahen Spaltenschema.
        $this->assertSame('Personalnummer;Datum;Lohnart;Stunden;Kostenstelle', $lines[0]);

        // Genau eine Summenzeile (work.normal) für den einen User:
        //  - Personalnummer aus users.personnel_number
        //  - Datum = Monatsletzter (31.01.2024)
        //  - Default-Lohnart 1000 (keine eigene wage_type_code)
        //  - 15,00 Stunden mit Komma-Dezimaltrenner
        $this->assertSame('4711;31.01.2024;1000;15,00;', $lines[1]);
        $this->assertCount(2, $lines, 'Kopfzeile + eine Datenzeile.');

        // Hash bleibt reproduzierbar über den gerenderten Inhalt.
        $this->assertSame(hash('sha256', $content), $built->payload_hash);
    }

    public function test_lexware_profile_renders_year_month_personnel_wagetype_value(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $user->forceFill(['personnel_number' => '4711'])->save();

        $this->seedAttendance($user, 8 * 60);    // 8 h Tag 15.
        $this->seedAttendance($user, 7 * 60, 16); // 7 h Tag 16. => 15 h gesamt

        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $export = $this->service->prepare(
            $this->organization,
            $this->year,
            $this->month,
            'lexware',
            'organization',
            actor: $admin,
        );
        $built = $this->service->build($export, $admin);

        $this->assertSame(TimeExportStatus::Ready, $built->status);
        $this->assertSame('csv', $built->file_format);

        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content), static fn (string $l): bool => $l !== ''));

        // Kopfzeile im Lexware-Spaltenschema.
        $this->assertSame('Jahr;Monat;Personalnummer;Lohnartnummer;Wert;Stundensatz', $lines[0]);

        // Jahr/Monat aus dem Zeitraum, Personalnummer 4711, Default-Lohnart 1000,
        // 15,00 Stunden mit Komma; Stundensatz leer (führt Lexware).
        $this->assertSame('2024;01;4711;1000;15,00;', $lines[1]);
        $this->assertCount(2, $lines, 'Kopfzeile + eine Datenzeile.');
    }

    public function test_datev_lodas_profile_falls_back_to_user_id_without_personnel_number(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser(); // ohne personnel_number
        $this->seedAttendance($user, 8 * 60);
        $this->approvedClosureFor($user, $admin);

        $this->actingAs($admin);
        $built = $this->service->build(
            $this->service->prepare($this->organization, $this->year, $this->month, 'datev', 'organization', actor: $admin),
            $admin,
        );

        $content = (string) Storage::disk('local')->get((string) $built->file_path);
        $lines = array_values(array_filter(explode("\r\n", $content), static fn (string $l): bool => $l !== ''));

        // Ohne Personalnummer fällt das Profil auf die User-ID zurück.
        $this->assertStringStartsWith($user->id . ';31.01.2024;1000;', $lines[1]);
    }

    // ── Helfer ─────────────────────────────────────────────────────────

    /**
     * Vollaudit 2026-07 (H6/M4): Der Export erzeugt Zeilen für
     * absence.vacation/absence.sick (Werktage), work.oncall/travel.time
     * (Stunden) sowie Zuschläge der Nicht-Intervall-Arten oncall/standby —
     * vorher entstand ausschließlich work.normal + Intervall-Zuschläge.
     */
    public function test_build_aggregates_absence_oncall_and_travel_wage_types(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60); // Mo 15.01.2024

        \App\Models\Vacation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_date' => '2024-01-02', // Di–Mi → 2 Werktage
            'end_date' => '2024-01-03',
            'type' => \App\Enums\Vacation\VacationType::Vacation,
            'status' => \App\Enums\Vacation\VacationStatus::Approved,
        ]);
        \App\Models\SickLeave::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_date' => '2024-01-08', // Mo–Di → 2 Werktage
            'end_date' => '2024-01-09',
            'kind' => \App\Enums\Sickness\SickLeaveKind::Initial,
        ]);
        \App\Models\OnCallShift::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_at' => '2024-01-20 08:00:00', // 8 h Bereitschaft
            'end_at' => '2024-01-20 16:00:00',
        ]);
        $project = \App\Models\Project::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Reisen',
            'status' => \App\Enums\Project\ProjectStatus::Active->value,
        ]);
        \App\Models\TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'date' => '2024-01-15',
            'minutes' => 90,
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Travel,
        ]);
        \App\Models\Surcharge\SurchargeRule::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'oncall25',
            'label' => 'Bereitschaft 25 %',
            'kind' => \App\Enums\Surcharge\SurchargeKind::OnCall,
            'percentage' => '25.00',
            'priority' => 0,
            'active' => true,
        ]);

        $this->approvedClosureFor($user, $admin);
        $this->actingAs($admin);
        $export = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $built = $this->service->build($export, $admin);

        $lines = $built->lines()->where('user_id', $user->id)->get()->keyBy('wage_type');
        $this->assertEqualsWithDelta(8.0, (float) $lines['work.normal']->quantity, 0.001);
        $this->assertEqualsWithDelta(2.0, (float) $lines['absence.vacation']->quantity, 0.001);
        $this->assertSame('d', $lines['absence.vacation']->unit);
        $this->assertEqualsWithDelta(2.0, (float) $lines['absence.sick']->quantity, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $lines['work.oncall']->quantity, 0.001);
        $this->assertEqualsWithDelta(1.5, (float) $lines['travel.time']->quantity, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $lines['surcharge.oncall25']->quantity, 0.001, 'Nicht-Intervall-Zuschlag aus OnCallShift-Minuten.');
        $this->assertSame((int) $built->rows_count, $built->lines()->count());
    }

    /**
     * Vollaudit 2026-07 (N6): Lösch-Pfad — nur nicht übergebene Läufe, Datei +
     * Zeilen verschwinden, die Spur bleibt als export.deleted-AuditLog.
     */
    public function test_delete_removes_run_but_keeps_audit_trail(): void {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->seedAttendance($user, 8 * 60);
        $this->approvedClosureFor($user, $admin);
        $this->actingAs($admin);

        $export = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $built = $this->service->build($export, $admin);
        $file = (string) $built->file_path;

        $this->service->delete($built, 'Fehlerhafte Periode, Neuaufbau folgt.', $admin);

        $this->assertDatabaseMissing('time_exports', ['id' => $built->id]);
        $this->assertSame(0, \App\Models\TimeExportLine::query()->where('time_export_id', $built->id)->count());
        Storage::disk('local')->assertMissing($file);
        $log = \App\Models\AuditLog::query()->where('event', 'export.deleted')->firstOrFail();
        $this->assertSame('Fehlerhafte Periode, Neuaufbau folgt.', $log->changes['reason']);

        // Übergebene Läufe sind tabu (Aufbewahrungspflicht).
        $export2 = $this->service->prepare($this->organization, $this->year, $this->month, 'generic', 'organization', actor: $admin);
        $built2 = $this->service->build($export2, $admin);
        $built2->forceFill(['status' => TimeExportStatus::Delivered])->save();
        $this->expectException(TimeExportException::class);
        $this->service->delete($built2->refresh(), 'Versuch', $admin);
    }

    private function seedAttendance(User $user, int $minutes, int $day = 15): void {
        $date = CarbonImmutable::create($this->year, $this->month, $day) ?? CarbonImmutable::now();
        Attendance::withoutEvents(function () use ($user, $minutes, $date): void {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => $date,
                'started_at' => $date->setTime(8, 0),
                'ended_at' => $date->setTime(8, 0)->addMinutes($minutes),
                'duration_minutes' => $minutes,
                'status' => AttendanceStatus::Closed,
            ]);
        });
    }

    private function approvedClosureFor(User $user, User $admin): MonthClosure {
        $this->actingAs($user);
        $closure = $this->closureService->getOrCreate($user, $this->year, $this->month);
        $closure = $this->closureService->submit($closure, $user);
        $this->actingAs($admin);

        return $this->closureService->approve($closure, $admin);
    }

    private function makeUser(): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->givePermissionTo([
            P::MonthViewOwn->value,
            P::MonthSubmitOwn->value,
        ]);
        $user->unsetRelation('permissions');

        return $user;
    }

    private function makeAdmin(): User {
        /** @var User $admin */
        $admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo([
            P::MonthViewOrganization->value,
            P::MonthApprove->value,
            P::MonthReject->value,
            P::MonthReopen->value,
            P::MonthLock->value,
            P::ExportTimeCreate->value,
            P::ExportTimeDeliver->value,
            P::ExportTimeDelete->value,
        ]);
        $admin->unsetRelation('permissions');

        return $admin;
    }
}
