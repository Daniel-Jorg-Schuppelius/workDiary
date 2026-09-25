<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalImportSourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Source;

use App\Services\Attendance\Import\AttendanceSpec;
use App\Services\Import\Source\Ical\{AttendanceIcalMapper, ProjectTimeIcalMapper};
use App\Services\Import\Source\{IcalImportSource, SourceRow};
use Tests\TestCase;

class IcalImportSourceTest extends TestCase {
    private string $path = '';

    protected function tearDown(): void {
        if ($this->path !== '' && is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    private function writeIcs(string $body): string {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n" . $body . "END:VCALENDAR\r\n";
        $this->path = (string) tempnam(sys_get_temp_dir(), 'ics_') . '.ics';
        file_put_contents($this->path, $ics);

        return $this->path;
    }

    /**
     * @return list<SourceRow>
     */
    private function collect(IcalImportSource $source): array {
        $rows = [];
        foreach ($source->rows(app(AttendanceSpec::class)) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function test_timed_vevent_maps_to_local_attendance_row(): void {
        // 07:00–09:00 UTC → 09:00–11:00 Europe/Berlin (Sommerzeit).
        $path = $this->writeIcs(
            "BEGIN:VEVENT\r\nUID:evt-1\r\nDTSTART:20260701T070000Z\r\nDTEND:20260701T090000Z\r\n" .
            "SUMMARY:Kundentermin\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n"
        );

        $source = new IcalImportSource($path, new AttendanceIcalMapper(), 'Europe/Berlin');
        $rows = $this->collect($source);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->isWarning());
        $this->assertSame([
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'break_minutes' => '',
            'note' => 'Kundentermin',
            'external_id' => 'evt-1',
        ], $rows[0]->data);
    }

    public function test_all_day_and_transparent_events_are_skipped_for_attendance(): void {
        $path = $this->writeIcs(
            "BEGIN:VEVENT\r\nUID:allday\r\nDTSTART;VALUE=DATE:20260702\r\nDTEND;VALUE=DATE:20260703\r\nSUMMARY:Betriebsausflug\r\nEND:VEVENT\r\n" .
            "BEGIN:VEVENT\r\nUID:free\r\nDTSTART:20260701T100000Z\r\nDTEND:20260701T110000Z\r\nTRANSP:TRANSPARENT\r\nSUMMARY:Frei\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n"
        );

        $source = new IcalImportSource($path, new AttendanceIcalMapper(), 'Europe/Berlin');
        $rows = $this->collect($source);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->isWarning());
        $this->assertTrue($rows[1]->isWarning());
    }

    public function test_transparent_event_is_kept_for_project_times(): void {
        $path = $this->writeIcs(
            "BEGIN:VEVENT\r\nUID:free\r\nDTSTART:20260701T100000Z\r\nDTEND:20260701T113000Z\r\nTRANSP:TRANSPARENT\r\nSUMMARY:Projekt X\r\nDESCRIPTION:Analyse\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n"
        );

        $source = new IcalImportSource($path, new ProjectTimeIcalMapper(), 'Europe/Berlin');
        $rows = [];
        foreach ($source->rows(app(AttendanceSpec::class)) as $row) {
            $rows[] = $row;
        }

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->isWarning());
        $this->assertSame('Projekt X', $rows[0]->data['project']);
        $this->assertSame('Analyse', $rows[0]->data['description']);
        $this->assertSame('12:00', $rows[0]->data['start_time']);
        $this->assertSame('13:30', $rows[0]->data['end_time']);
    }

    public function test_recurring_series_is_expanded_in_window_with_exdate_and_override(): void {
        // Wöchentlich montags 08:00–12:00 Europe/Berlin; 13.07. entfällt (EXDATE),
        // 20.07. ist auf 09:00–13:00 verschoben (RECURRENCE-ID) — MVP-885.
        $path = $this->writeIcs(
            "BEGIN:VEVENT\r\nUID:serie-1\r\nDTSTART;TZID=Europe/Berlin:20260706T080000\r\nDTEND;TZID=Europe/Berlin:20260706T120000\r\n" .
            "RRULE:FREQ=WEEKLY;BYDAY=MO\r\nEXDATE;TZID=Europe/Berlin:20260713T080000\r\nSUMMARY:Montagsdienst\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n" .
            "BEGIN:VEVENT\r\nUID:serie-1\r\nRECURRENCE-ID;TZID=Europe/Berlin:20260720T080000\r\nDTSTART;TZID=Europe/Berlin:20260720T090000\r\nDTEND;TZID=Europe/Berlin:20260720T130000\r\n" .
            "SUMMARY:Montagsdienst\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n"
        );

        $window = [new \DateTimeImmutable('2026-07-01 00:00', new \DateTimeZone('Europe/Berlin')), new \DateTimeImmutable('2026-07-28 00:00', new \DateTimeZone('Europe/Berlin'))];
        $rows = $this->collect(new IcalImportSource($path, new AttendanceIcalMapper(), 'Europe/Berlin', [], $window));

        $this->assertSame(
            [['2026-07-06', '08:00', 'serie-1#20260706T060000'], ['2026-07-20', '09:00', 'serie-1#20260720T070000'], ['2026-07-27', '08:00', 'serie-1#20260727T060000']],
            array_map(static fn (SourceRow $r): array => [$r->data['date'], $r->data['start_time'], $r->data['external_id']], $rows),
        );
    }

    public function test_series_outside_window_is_reported_and_without_window_only_base_instance(): void {
        $path = $this->writeIcs(
            "BEGIN:VEVENT\r\nUID:serie-2\r\nDTSTART:20260105T070000Z\r\nDTEND:20260105T110000Z\r\nRRULE:FREQ=DAILY;COUNT=3\r\n" .
            "SUMMARY:Januar\r\nORGANIZER:mailto:worker@example.com\r\nEND:VEVENT\r\n"
        );

        $window = [new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-08-01')];
        $rows = $this->collect(new IcalImportSource($path, new AttendanceIcalMapper(), 'Europe/Berlin', [], $window));
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->isWarning());

        $rows = $this->collect(new IcalImportSource($path, new AttendanceIcalMapper(), 'Europe/Berlin'));
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->isWarning());
        $this->assertSame('serie-2', $rows[1]->data['external_id']);
    }
}
