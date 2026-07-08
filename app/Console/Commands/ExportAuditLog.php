<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportAuditLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use CommonToolkit\Enums\Common\CSV\QuotingStyle;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use RuntimeException;
use ZipArchive;

/**
 * Exportiert die revisionssicheren Audit-Ketten maschinell auswertbar (GoBD):
 * je Kette eine CSV (mit Kopfzeile) plus ein manifest.json mit Datensatz-
 * beschreibung, Zeilenzahl, Kettenkopf-Hash und Integritätsstatus. Der Export
 * ist damit selbst prüfbar – der head_hash bindet den Inhalt kryptografisch.
 */
class ExportAuditLog extends Command {
    protected $signature = 'audit:export {--chain= : Nur diese Kette exportieren} {--disk=local} {--dir=audit-exports}';

    protected $description = 'Exportiert die Audit-Ketten als ZIP (CSV + manifest.json) für GoBD-Auswertung.';

    public function handle(): int {
        /** @var array<string, class-string> $chains */
        $chains = config('audit.chains', []);
        $only = $this->option('chain');
        if ($only !== null && ! isset($chains[$only])) {
            $this->error("Unbekannte Kette: {$only}. Erlaubt: " . implode(', ', array_keys($chains)));

            return self::INVALID;
        }

        $disk = Storage::disk((string) $this->option('disk'));
        $dir = (string) $this->option('dir');
        $disk->makeDirectory($dir);

        $stamp = Carbon::now()->format('Ymd-His');
        $relPath = $dir . '/audit-' . $stamp . '.zip';
        $absPath = $disk->path($relPath);

        $zip = new ZipArchive;
        if ($zip->open($absPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Konnte ZIP nicht öffnen: ' . $absPath);
        }

        $manifest = [
            'generated_at' => Carbon::now()->toIso8601String(),
            // Frist je Rechtsraum (Restpunkt 67) — Fallback: config-Default.
            'retention_years' => (int) (config('retention.areas.audit_logs.years.' . strtoupper((string) config('retention.default_region', 'DE'))) ?? config('audit.retention_years', 10)),
            'note' => 'Append-only Hash-Ketten (SHA-256). Integrität via "php artisan audit:verify" '
                . 'bzw. anhand des head_hash je Kette prüfbar.',
            'chains' => [],
        ];

        foreach ($chains as $table => $_modelClass) {
            if ($only !== null && $only !== $table) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $zip->addFromString("data/{$table}.csv", $this->toCsv($table, $columns));

            $head = DB::table('audit_chain_heads')->where('chain', $table)->first();
            $integrityOk = $this->call('audit:verify', ['--chain' => $table]) === self::SUCCESS;

            $manifest['chains'][$table] = [
                'rows' => DB::table($table)->count(),
                'columns' => $columns,
                'head_hash' => $head->head_hash ?? null,
                'height' => (int) ($head->height ?? 0),
                'integrity_ok' => $integrityOk,
            ];
        }

        $zip->addFromString('manifest.json', JsonHelper::encode(
            $manifest,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ));
        $zip->close();

        $this->info('Audit-Export erstellt: ' . $relPath);

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $columns
     */
    private function toCsv(string $table, array $columns): string {
        // GoBD: QuotingStyle::FPUTCSV + "\n" ist byte-identisch zum früheren
        // fputcsv (Paritätstest im Toolkit) — Export-Bytes bleiben stabil.
        $csv = StringHelper::encodeLine($columns, ',', '"', QuotingStyle::FPUTCSV) . "\n";
        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = (string) ($row->{$col} ?? '');
            }
            $csv .= StringHelper::encodeLine($line, ',', '"', QuotingStyle::FPUTCSV) . "\n";
        }

        return $csv;
    }
}
