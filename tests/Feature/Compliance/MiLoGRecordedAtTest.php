<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MiLoGRecordedAtTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Models\Platform\User;
use App\Models\Time\Attendance;
use App\Services\Attendance\AttendanceClockService;
use App\Services\Attendance\Import\AttendanceSpec;
use App\Services\Compliance\{AttendanceComplianceChecker, AttendanceComplianceFinding, ComplianceScanService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1015: MiLoG-Aufzeichnungsfrist nach dem ursprünglichen Erfassungszeitpunkt. */
final class MiLoGRecordedAtTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @return list<string> Arbeitstage mit Befund „verspätet erfasst“ */
    private function lateDays(User $user): array {
        $findings = app(ComplianceScanService::class)->findingsForRange($this->organization, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-29'));

        return array_values(array_map(static fn (AttendanceComplianceFinding $f): string => $f->date, array_filter(
            $findings[$user->id] ?? [],
            static fn (AttendanceComplianceFinding $f): bool => $f->kind === AttendanceComplianceChecker::KIND_LATE_RECORDING,
        )));
    }

    public function test_the_deadline_follows_the_original_recording(): void {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00', 'UTC'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'worker@example.com']);

        // Offline gestempelt am 2. September, erst heute übertragen: rechtzeitig erfasst.
        $clock = app(AttendanceClockService::class);
        $clock->clockIn($user, ['started_at' => '2026-09-02 08:00:00']);
        $stamped = $clock->clockOut($user, ['ended_at' => '2026-09-02 16:00:00']);
        $this->assertSame('2026-09-02 16:00:00', $stamped?->recorded_at?->format('Y-m-d H:i:s'));

        // Import ohne Quellangabe: Frist ungeprüft; mit Quellangabe zwölf Tage später: verspätet.
        $spec = app(AttendanceSpec::class);
        foreach ([['2026-09-03', null], ['2026-09-04', '16.09.2026 09:30']] as [$date, $recorded]) {
            $row = $spec->normalize(array_filter(['user_email' => 'worker@example.com', 'date' => $date, 'start_time' => '08:00', 'end_time' => '16:00', 'erfasst am' => null, 'recorded_at' => $recorded]));
            $this->assertSame([], $spec->validateRow($row, $this->organization));
            $spec->upsert($row, $this->organization);
        }
        $this->assertNull(Attendance::query()->whereDate('date', '2026-09-03')->sole()->recorded_at);
        $this->assertSame(AttendanceStatus::Closed, Attendance::query()->whereDate('date', '2026-09-03')->sole()->status);
        $this->assertSame('2026-09-16 09:30:00', Attendance::query()->whereDate('date', '2026-09-04')->sole()->recorded_at?->format('Y-m-d H:i:s'));

        // Manuell nacherfasst ohne Erfassungszeitpunkt: created_at zählt wie bisher.
        Attendance::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $user->id, 'started_at' => '2026-09-05 08:00:00', 'ended_at' => '2026-09-05 16:00:00',
            'source' => AttendanceSource::Manual, 'status' => AttendanceStatus::Closed,
        ]);

        $this->assertSame(['2026-09-04', '2026-09-05'], $this->lateDays($user));

        $invalid = $spec->normalize(['user_email' => 'worker@example.com', 'date' => '2026-09-06', 'start_time' => '08:00', 'recorded_at' => 'gestern']);
        $this->assertNotSame([], $spec->validateRow($invalid, $this->organization));
    }
}
