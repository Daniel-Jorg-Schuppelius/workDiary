<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WritesReportCsv.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use App\Models\{AuditLog, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use CommonToolkit\Helper\Data\CSV\StringHelper;
use Illuminate\Http\{Request, Response};

/**
 * MVP-043: Zentrales Helper-Trait für CSV-Exporte der Report-Controller.
 *
 * Liefert UTF-8/BOM-CSV mit optionalen Meta-Kommentarzeilen
 * (`#report:`, `#generated:`, `#filter_hash:`) und stellt einen
 * stabilen 8-stelligen Filter-Hash (SHA-256) bereit, der vom Audit-
 * Eintrag wiederverwendet wird (vgl. report.exported).
 */
trait WritesReportCsv {
    /**
     * Liefert eine CSV-Antwort mit optionalen Meta-Kommentarzeilen.
     *
     * A9: Mit übergebenem $request wird der Audit-Eintrag `report.exported`
     * direkt hier geschrieben — Aufrufer brauchen kein eigenes auditExport mehr.
     * $auditReportCode für Reports, deren Metazeile ein Versions-Suffix trägt,
     * der Audit-Code aber stabil bleiben soll (Agile/Helpdesk).
     *
     * @param  list<list<string|int|float|null>>  $rows
     * @param  array<string, mixed>               $filters
     */
    protected function csvWithMetadata(
        array $rows,
        string $filename,
        string $reportCode,
        array $filters,
        ?Request $request = null,
        ?string $auditReportCode = null,
    ): Response {
        if ($request !== null) {
            $this->auditExport($request, $auditReportCode ?? $reportCode, 'csv', $filters);
        }

        $delimiter = (string) config('reports.csv_delimiter', ';');
        $delimiter = $delimiter === '' ? ';' : $delimiter[0];

        $csv = '';

        if ((bool) config('reports.csv_meta_lines', true)) {
            $csv .= '#report:' . $reportCode . "\r\n";
            $csv .= '#generated:' . CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z') . "\r\n";
            $csv .= '#filter_hash:' . $this->reportFilterHash($filters) . "\r\n";
        }

        foreach ($rows as $row) {
            $csv .= StringHelper::encodeLine($row, $delimiter) . "\r\n";
        }

        return response(\CommonToolkit\Helper\Data\StringHelper::prependBom($csv), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Audit-Eintrag `report.exported` mit vollem Filter-Hash (vgl. Meta-Zeile).
     *
     * @param  array<string, mixed>  $filters
     */
    protected function auditExport(Request $request, string $reportCode, string $format, array $filters): void {
        $user = $request->user();
        if (! $user instanceof User || $user->organization_id === null) {
            return;
        }

        AuditLog::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'event' => 'report.exported',
            'auditable_type' => self::class, // bindet im Trait an den konkreten Controller
            'auditable_id' => 0,
            'changes' => [
                'report_code' => $reportCode,
                'format' => $format,
                'filter_hash' => $this->reportFilterHashFull($filters),
                'filters' => $filters,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /**
     * Stabiler 8-stelliger Filter-Hash für Audit & Meta-Zeile.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function reportFilterHash(array $filters): string {
        return substr($this->reportFilterHashFull($filters), 0, 8);
    }

    /**
     * Voller SHA-256-Hash (Audit-Log) — identische Normalisierung.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function reportFilterHashFull(array $filters): string {
        $normalized = $this->normalizeForHash($filters);
        $payload = JsonHelper::encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $hash = CryptoHelper::hash($payload);

        return $hash;
    }

    /**
     * Sortiert Filter-Keys rekursiv für reproduzierbare Hashes.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeForHash(array $filters): array {
        ksort($filters);
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $filters[$key] = $this->normalizeForHash($value);
            }
        }

        return $filters;
    }
}
