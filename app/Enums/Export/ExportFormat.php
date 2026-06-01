<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Export;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ausgabeformat eines Datentransfer-Exports.
 */
enum ExportFormat: string implements HasLabel {
    use HasOptions;

    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function label(): string {
        return (string) __('export.format.' . $this->value);
    }

    public function extension(): string {
        return $this->value;
    }

    public function mime(): string {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => \App\Support\XlsxExport::MIME,
        };
    }
}
