<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CSV-Import für Kunden.
 *
 * Erwartet eine Kopfzeile mit deutschen oder englischen Spaltennamen
 * (siehe self::HEADER_MAP). Trennzeichen wird automatisch erkannt
 * (Semikolon bevorzugt, sonst Komma oder Tab).
 *
 * Rückgabe: ImportResult mit Anzahl angelegt/aktualisiert/übersprungen + Fehlerliste.
 */
class CustomerCsvImporter
{
    /** Map: Header-Bezeichner (klein, getrimmt) → DB-Spalte */
    private const HEADER_MAP = [
        'name' => 'name',
        'kunde' => 'name',
        'number' => 'number',
        'nummer' => 'number',
        'kundennummer' => 'number',
        'company' => 'company',
        'firma' => 'company',
        'vat_id' => 'vat_id',
        'ust-idnr.' => 'vat_id',
        'ustid' => 'vat_id',
        'contact_name' => 'contact_name',
        'ansprechpartner' => 'contact_name',
        'email' => 'email',
        'e-mail' => 'email',
        'phone' => 'phone',
        'telefon' => 'phone',
        'mobile' => 'mobile',
        'mobil' => 'mobile',
        'fax' => 'fax',
        'homepage' => 'homepage',
        'website' => 'homepage',
        'address' => 'address',
        'adresse' => 'address',
        'street' => 'address_street',
        'straße' => 'address_street',
        'strasse' => 'address_street',
        'zip' => 'address_zip',
        'plz' => 'address_zip',
        'city' => 'address_city',
        'ort' => 'address_city',
        'country' => 'country',
        'land' => 'country',
        'currency' => 'currency',
        'währung' => 'currency',
        'waehrung' => 'currency',
        'hourly_rate' => 'hourly_rate',
        'stundensatz' => 'hourly_rate',
        'internal_rate' => 'internal_rate',
        'comment' => 'comment',
        'notiz' => 'comment',
        'invoice_text' => 'invoice_text',
        'rechnungstext' => 'invoice_text',
        'billable' => 'billable',
        'abrechenbar' => 'billable',
    ];

    /**
     * @return array{created:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function import(UploadedFile $file, ?int $organizationId): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Datei nicht lesbar.']];
        }

        // BOM überspringen
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Trennzeichen erkennen anhand erster Zeile
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Datei ist leer.']];
        }
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3);
        }

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if ($headerRow === false) {
            fclose($handle);
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Kopfzeile fehlt.']];
        }

        $columns = $this->mapHeaders($headerRow);
        if (! in_array('name', $columns, true)) {
            fclose($handle);
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Pflichtspalte "Name" nicht gefunden.']];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $lineNo = 1; // Header

        $userId = Auth::id();

        DB::transaction(function () use (
            $handle, $delimiter, $columns, $organizationId, $userId,
            &$created, &$updated, &$skipped, &$errors, &$lineNo
        ): void {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNo++;
                if ($row === [null]) {
                    continue;
                }

                $data = [];
                foreach ($columns as $i => $col) {
                    if ($col === null) {
                        continue;
                    }
                    $val = trim((string) ($row[$i] ?? ''));
                    if ($val === '') {
                        continue;
                    }
                    $data[$col] = $this->castValue($col, $val);
                }

                $name = $data['name'] ?? null;
                if ($name === null || $name === '') {
                    $skipped++;
                    continue;
                }

                $data['organization_id'] ??= $organizationId;
                $data['currency'] ??= 'EUR';

                $existing = null;
                if (! empty($data['number'])) {
                    $existing = Customer::query()
                        ->where('organization_id', $organizationId)
                        ->where('number', $data['number'])
                        ->first();
                }

                try {
                    if ($existing !== null) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        Customer::create($data + ['created_by' => $userId]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = sprintf('Zeile %d: %s', $lineNo, $e->getMessage());
                    $skipped++;
                }
            }
        });

        fclose($handle);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($candidates);
        $best = (string) array_key_first($candidates);
        return $candidates[$best] > 0 ? $best : ';';
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array<int, string|null> indexed by CSV column position, value is DB column name or null
     */
    private function mapHeaders(array $headerRow): array
    {
        $out = [];
        foreach ($headerRow as $i => $h) {
            $key = strtolower(trim((string) $h));
            $out[$i] = self::HEADER_MAP[$key] ?? null;
        }
        return $out;
    }

    private function castValue(string $col, string $val): mixed
    {
        return match ($col) {
            'billable' => in_array(strtolower($val), ['1', 'ja', 'yes', 'true', 'wahr'], true),
            'hourly_rate', 'internal_rate' => (float) str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $val),
            'country', 'currency' => strtoupper($val),
            default => $val,
        };
    }
}
