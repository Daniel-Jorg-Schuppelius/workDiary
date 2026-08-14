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

use App\Services\Import\Source\Ical\{AttendanceIcalMapper, ProjectTimeIcalMapper};
use App\Services\Import\Source\{IcalImportSource, SourceRow};
use App\Services\Import\Specs\AttendanceSpec;
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
}
