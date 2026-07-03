<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportRunner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\Export\{ExportFormat, ExportRunState};
use App\Models\{ExportRun, Organization, User};
use App\Support\XlsxExport;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Erzeugt aus einer {@see ExportSpec} eine CSV- oder XLSX-Datei, legt sie im
 * Tenant-Storage ab und protokolliert den Lauf als {@see ExportRun}.
 *
 * Die Kopfzeile besteht aus den kanonischen Spalten-Codes der Spec; bei
 * Import-Entitäten ist das Resultat daher ohne Anpassung re-importierbar
 * (verlustfreier Round-Trip).
 */
final class ExportRunner {
    public const DISK = 'local';
    private const BOM = \CommonToolkit\Helper\Data\StringHelper::BOM_UTF8;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function run(
        ExportSpec $spec,
        Organization $organization,
        array $filters,
        ExportFormat $format,
        ?User $user,
    ): ExportRun {
        $filename = $this->buildFilename($spec, $organization, $format);
        $relativePath = $this->buildPath($organization, $filename);

        $run = ExportRun::create([
            'organization_id' => $organization->id,
            'entity' => $spec->entity(),
            'format' => $format,
            'state' => ExportRunState::Preparing,
            'filters' => $filters === [] ? null : $filters,
            'output_filename' => $filename,
            'storage_path' => $relativePath,
            'rows_total' => 0,
            'created_by_user_id' => $user?->id,
        ]);

        try {
            $rowsTotal = $this->write($spec, $organization, $filters, $format, $relativePath);

            $run->rows_total = $rowsTotal;
            $run->state = ExportRunState::Ready;
            $run->save();
        } catch (Throwable $e) {
            $run->state = ExportRunState::Failed;
            $run->error_message = mb_substr($e->getMessage(), 0, 1000);
            $run->save();
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function write(
        ExportSpec $spec,
        Organization $organization,
        array $filters,
        ExportFormat $format,
        string $relativePath,
    ): int {
        $columns = $spec->columns();
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(dirname($relativePath));
        $absolutePath = $disk->path($relativePath);

        $rowsTotal = 0;

        if ($format === ExportFormat::Csv) {
            $handle = fopen($absolutePath, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Export-Datei konnte nicht geöffnet werden.');
            }
            fwrite($handle, self::BOM);
            fwrite($handle, StringHelper::encodeLine($columns, ';') . "\r\n");
            foreach ($spec->query($organization, $filters) as $model) {
                $row = $spec->toRow($model);
                $cells = array_map(static fn(string $code): mixed => $row[$code] ?? '', $columns);
                fwrite($handle, StringHelper::encodeLine($cells, ';') . "\r\n");
                $rowsTotal++;
            }
            fclose($handle);

            return $rowsTotal;
        }

        // XLSX: Generator, der nebenbei die Zeilen zählt.
        $rows = (function () use ($spec, $organization, $filters, $columns, &$rowsTotal): \Generator {
            foreach ($spec->query($organization, $filters) as $model) {
                $row = $spec->toRow($model);
                $rowsTotal++;
                yield array_map(static fn(string $code) => $row[$code] ?? '', $columns);
            }
        })();

        XlsxExport::saveToPath($absolutePath, $columns, $rows);

        return $rowsTotal;
    }

    private function buildFilename(ExportSpec $spec, Organization $organization, ExportFormat $format): string {
        return sprintf(
            '%s_%s.%s',
            $spec->entity()->value,
            CarbonImmutable::now()->format('Ymd_His'),
            $format->extension(),
        );
    }

    private function buildPath(Organization $organization, string $filename): string {
        return sprintf(
            'exports/data/%d/%s/%s',
            $organization->id,
            CarbonImmutable::now()->format('Y-m'),
            $filename,
        );
    }
}
