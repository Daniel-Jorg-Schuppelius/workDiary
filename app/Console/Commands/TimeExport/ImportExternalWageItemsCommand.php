<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportExternalWageItemsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\TimeExport;

use App\Models\{ExternalWageItem, Organization, User};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Console\Command;

/**
 * Import externer vergütungsrelevanter Positionen (Feature-103-Delta, Q1
 * „Import von Bewegungsdaten"). CSV mit Semikolon-Trennung und Kopfzeile:
 *
 *   personnel_number;date;wage_type_code;quantity;unit;note
 *
 * Statt der Personalnummer wird auch die E-Mail-Adresse akzeptiert.
 * Bestehende Positionen gleicher (Nutzer, Datum, Lohnart, Quelle) werden
 * ersetzt — der Import ist damit je Datei wiederholbar.
 */
class ImportExternalWageItemsCommand extends Command {
    protected $signature = 'wage-items:import
        {file : Pfad zur CSV-Datei (Semikolon-getrennt, mit Kopfzeile)}
        {--org= : Organisations-ID}
        {--source=csv : Quellkennung für Dedup/Nachvollziehbarkeit}';

    protected $description = 'Importiert externe vergütungsrelevante Positionen (Essensgeld, Kilometer, Zulagen) für den Zeitwirtschafts-Export.';

    public function handle(): int {
        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error(sprintf('Datei nicht lesbar: %s', $file));

            return self::FAILURE;
        }

        $organization = Organization::query()->find((int) $this->option('org'));
        if ($organization === null) {
            $this->error('Organisation nicht gefunden (--org).');

            return self::FAILURE;
        }
        $source = (string) $this->option('source');

        // Toolkit-CSV statt fgetcsv-Handparser (Vollscan 2026-08-23, C5):
        // Delimiter-Erkennung (Lohn-Exporte kommen mit ; ODER ,), BOM/Quoting.
        try {
            $delimiter = \CommonToolkit\Parsers\CSVDocumentParser::detectDelimiter($file);
        } catch (\Throwable) {
            $this->error('Leere Datei oder Datei konnte nicht geöffnet werden.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        foreach (\App\Support\Toolkit\CsvFacade::streamAssoc($file, $delimiter) as $row) {
            $data = [];
            foreach ($row as $column => $value) {
                $data[strtolower(trim($column))] = $value;
            }
            $who = trim((string) ($data['personnel_number'] ?? ''));
            $date = trim((string) ($data['date'] ?? ''));
            $code = trim((string) ($data['wage_type_code'] ?? ''));
            $quantity = NumberHelper::normalizeDecimalStringOrNull((string) ($data['quantity'] ?? '')) ?? '';

            if ($who === '' || $date === '' || $code === '' || ! is_numeric($quantity)) {
                $skipped++;

                continue;
            }

            $user = User::withoutGlobalScopes()
                ->where('organization_id', $organization->getKey())
                ->where(fn ($q) => $q->where('personnel_number', $who)->orWhere('email', $who))
                ->first();
            if ($user === null) {
                $skipped++;

                continue;
            }

            // Wiederholbarer Import: gleiche (Nutzer, Tag, Lohnart, Quelle) ersetzen.
            ExternalWageItem::query()
                ->where('user_id', $user->getKey())
                ->whereDate('item_date', $date)
                ->where('wage_type_code', $code)
                ->where('source', $source)
                ->delete();

            ExternalWageItem::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'item_date' => $date,
                'wage_type_code' => $code,
                'quantity' => $quantity,
                'unit' => trim((string) ($data['unit'] ?? '')) !== '' ? trim((string) $data['unit']) : 'unit',
                'note' => trim((string) ($data['note'] ?? '')) !== '' ? trim((string) $data['note']) : null,
                'source' => $source,
            ]);
            $created++;
        }

        $this->info(sprintf('Import: %d Positionen übernommen, %d Zeilen übersprungen.', $created, $skipped));

        return self::SUCCESS;
    }
}
