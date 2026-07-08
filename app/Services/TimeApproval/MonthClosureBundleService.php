<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureBundleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\{Attachment, Attendance, AuditLog, MonthClosure, Organization, TimeEntry, User};
use App\Services\Compliance\AttendanceComplianceChecker;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Builders\CSVDocumentBuilder;
use CommonToolkit\Entities\CSV\DataLine;
use CommonToolkit\Generators\CSV\CSVGenerator;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Prüfexport-Bundle für geprüfte Zeiträume (Feature 006, Rang 40): bündelt
 * je freigegebenem/gesperrtem Monat Zeiten, Anwesenheiten, das
 * Freigabe-Protokoll (MonthClosureEvents), live gerechnete
 * ArbZG-Compliance-Befunde und den Audit-Auszug des Monatsabschlusses als
 * ZIP mit Manifest (SHA-256 je Datei; Paket-Hash über die `name:hash`-Zeilen
 * — GoBD-Muster, deterministisch unabhängig von ZIP-Metadaten). Ablage als
 * Attachment am MonthClosure (`meta_type=audit_bundle`), auditiert.
 */
class MonthClosureBundleService {
    public const META_TYPE = 'audit_bundle';

    private const CSV_DELIMITER = ';';

    private const CSV_ENCLOSURE = '"';

    /**
     * @return array{attachment: Attachment, filename: string, content: string, package_sha256: string, reused: bool}
     */
    public function package(MonthClosure $closure, ?User $actor = null): array {
        if (! in_array($closure->status, [MonthClosureStatus::Approved, MonthClosureStatus::Locked], true)) {
            throw new RuntimeException((string) __('Prüfpakete gibt es nur für freigegebene oder gesperrte Monate.'));
        }

        $files = $this->buildFiles($closure);

        // Manifest + deterministischer Paket-Hash (GoBD-Muster: Hash über die
        // name:hash-Zeilen, nicht über die ZIP-Bytes — mtime-unabhängig).
        $manifestLines = [];
        foreach ($files as $name => $content) {
            $manifestLines[] = $name . ':' . hash('sha256', $content);
        }
        $packageSha256 = hash('sha256', implode("\n", $manifestLines));
        $files['manifest.txt'] = implode("\n", array_merge($manifestLines, ['package:' . $packageSha256])) . "\n";

        $filename = sprintf('pruefpaket-%s-%s.zip', $closure->periodLabel(), substr($packageSha256, 0, 8));
        $path = sprintf('month-closures/%d/%d/%s', (int) $closure->organization_id, (int) $closure->id, $filename);

        // Reproduzierbarkeit: identischer Datenstand → identischer Hash → das
        // bestehende Attachment wird wiederverwendet statt dupliziert.
        /** @var Attachment|null $existing */
        $existing = $closure->attachments()
            ->where('meta_type', self::META_TYPE)
            ->where('path', $path)
            ->first();
        if ($existing !== null && Storage::disk('local')->exists($path)) {
            return [
                'attachment' => $existing,
                'filename' => $filename,
                'content' => (string) Storage::disk('local')->get($path),
                'package_sha256' => $packageSha256,
                'reused' => true,
            ];
        }

        $binary = $this->zip($files);
        Storage::disk('local')->put($path, $binary);

        /** @var Attachment $attachment */
        $attachment = $closure->attachments()->create([
            'organization_id' => $closure->organization_id,
            'user_id' => $actor?->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $filename,
            'mime' => 'application/zip',
            'size' => strlen($binary),
            'meta_type' => self::META_TYPE,
        ]);

        $closure->audit('month_closure.bundle_exported', [
            'package_sha256' => $packageSha256,
            'files' => array_keys($files),
            'attachment_id' => $attachment->id,
        ]);

        return [
            'attachment' => $attachment,
            'filename' => $filename,
            'content' => $binary,
            'package_sha256' => $packageSha256,
            'reused' => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildFiles(MonthClosure $closure): array {
        $from = CarbonImmutable::create((int) $closure->period_year, (int) $closure->period_month, 1);
        if (! $from instanceof CarbonImmutable) {
            throw new RuntimeException('Ungültige Periode.');
        }
        $from = $from->startOfMonth();
        $to = $from->endOfMonth();
        $userId = (int) $closure->user_id;

        $files = [];

        // Zeiten (TimeEntries des Monats, deterministisch sortiert).
        $rows = [['Datum', 'Minuten', 'Projekt', 'Beschreibung', 'Abrechenbar']];
        TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $closure->organization_id)
            ->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('project:id,name')
            ->orderBy('date')->orderBy('id')
            ->get()
            ->each(function (TimeEntry $entry) use (&$rows): void {
                $rows[] = [
                    (string) $entry->date?->toDateString(),
                    (string) (int) $entry->minutes,
                    (string) ($entry->project->name ?? ''),
                    (string) ($entry->description ?? ''),
                    $entry->billable ? '1' : '0',
                ];
            });
        $files['zeiten.csv'] = $this->csv($rows);

        // Anwesenheiten.
        $rows = [['Datum', 'Kommen', 'Gehen', 'Dauer_Minuten', 'Status']];
        $attendances = Attendance::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $closure->organization_id)
            ->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')->orderBy('id')
            ->get();
        foreach ($attendances as $attendance) {
            $rows[] = [
                (string) $attendance->date?->toDateString(),
                (string) ($attendance->started_at?->format('H:i') ?? ''),
                (string) ($attendance->ended_at?->format('H:i') ?? ''),
                (string) (int) $attendance->duration_minutes,
                (string) ($attendance->status->value ?? ''),
            ];
        }
        $files['anwesenheiten.csv'] = $this->csv($rows);

        // Freigabe-Protokoll (MonthClosureEvents, append-only).
        $rows = [['Zeitpunkt', 'Ereignis', 'Akteur', 'Notiz']];
        foreach ($closure->events()->with('actor:id,name')->get() as $event) {
            $rows[] = [
                (string) $event->created_at->toIso8601String(),
                (string) $event->event,
                (string) ($event->actor->name ?? ''),
                (string) ($event->note ?? ''),
            ];
        }
        $files['freigabe-protokoll.csv'] = $this->csv($rows);

        // ArbZG-Compliance-Befunde (live gerechnet, gleiche Logik wie Report).
        $files['compliance-befunde.csv'] = $this->csv($this->complianceRows($closure, $from, $to, $attendances));

        // Audit-Auszug zum Monatsabschluss (Hash-Kette bleibt in audit_logs
        // prüfbar). Die Paket-Erzeugung selbst bleibt draußen — sonst änderte
        // jeder Export den Datenstand und der Paket-Hash wäre nie reproduzierbar.
        $rows = [['Zeitpunkt', 'Ereignis', 'Benutzer', 'Details']];
        AuditLog::query()
            ->where('auditable_type', $closure->getMorphClass())
            ->where('auditable_id', $closure->getKey())
            ->where('event', '!=', 'month_closure.bundle_exported')
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->each(function (AuditLog $log) use (&$rows): void {
                $rows[] = [
                    (string) $log->created_at?->toIso8601String(),
                    (string) $log->event,
                    (string) ($log->user->name ?? ''),
                    (string) json_encode($log->getAttribute('changes'), JSON_UNESCAPED_UNICODE),
                ];
            });
        $files['audit-auszug.csv'] = $this->csv($rows);

        return $files;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Attendance>  $attendances
     * @return list<list<string>>
     */
    private function complianceRows(MonthClosure $closure, CarbonImmutable $from, CarbonImmutable $to, $attendances): array {
        $rows = [['Datum', 'Regel', 'Schwere', 'Wert_Minuten', 'Schwelle_Minuten']];

        /** @var Organization|null $org */
        $org = Organization::query()->withoutGlobalScopes()->find($closure->organization_id);
        $checker = AttendanceComplianceChecker::forOrganization($org);
        if (! $checker->enabled()) {
            return $rows;
        }

        $tz = Tz::current();
        $spansByDate = [];
        foreach ($attendances as $attendance) {
            if ($attendance->started_at === null || $attendance->ended_at === null) {
                continue;
            }
            if ($attendance->status === AttendanceStatus::Cancelled || $attendance->status === AttendanceStatus::Open) {
                continue;
            }
            $start = CarbonImmutable::parse((string) $attendance->started_at)->setTimezone($tz);
            $spansByDate[$start->toDateString()][] = [
                'started_at' => $start,
                'ended_at' => CarbonImmutable::parse((string) $attendance->ended_at)->setTimezone($tz),
                'break_minutes' => (int) ($attendance->break_minutes_total ?? 0),
            ];
        }

        foreach ($checker->checkUser((int) $closure->user_id, $spansByDate) as $finding) {
            if ($finding->date < $from->toDateString() || $finding->date > $to->toDateString()) {
                continue;
            }
            $rows[] = [
                $finding->date,
                $finding->kind,
                $finding->severity,
                (string) $finding->value,
                (string) $finding->threshold,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): string {
        $builder = new CSVDocumentBuilder(self::CSV_DELIMITER, self::CSV_ENCLOSURE);
        foreach ($rows as $row) {
            $builder->addRow(new DataLine($row, self::CSV_DELIMITER, self::CSV_ENCLOSURE));
        }

        return (new CSVGenerator())->generate($builder->build(), includeHeader: false);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string {
        $dir = storage_path('app/month-closure-tmp/' . bin2hex(random_bytes(8)));
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $paths = [];
        try {
            foreach ($files as $name => $content) {
                $path = $dir . '/' . $name;
                file_put_contents($path, $content);
                $paths[] = $path;
            }
            $zipPath = $dir . '/package.zip';
            ZipFile::create($paths, $zipPath);
            $binary = (string) file_get_contents($zipPath);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }

        return $binary;
    }
}
