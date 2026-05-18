<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCsvImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Support\Toolkit\CsvFacade;
use App\Support\Toolkit\NumberFacade;
use App\Support\Toolkit\StringFacade;
use CommonToolkit\Enums\CountryCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * CSV-Import für Kunden.
 *
 * Erwartet eine Kopfzeile mit deutschen oder englischen Spaltennamen
 * (siehe self::HEADER_MAP). Trennzeichen wird automatisch erkannt
 * (Semikolon bevorzugt, sonst Komma oder Tab) via CommonToolkit.
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
     * @return array{created:int, updated:int, skipped:int, errors:list<string>}
     */
    public function import(UploadedFile $file, ?int $organizationId): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [(string) __('errors.csv.unreadable')]];
        }

        try {
            $headerRow = CsvFacade::readHeader($path);
        } catch (Throwable $e) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [(string) __('errors.csv.header_missing', ['error' => $e->getMessage()])]];
        }

        $columns = $this->mapHeaders($headerRow);
        if (! in_array('name', $columns, true)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [(string) __('errors.csv.name_column_missing')]];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $userId = Auth::id();

        DB::transaction(function () use ($path, $columns, $organizationId, $userId, &$created, &$updated, &$skipped, &$errors): void {
            foreach (CsvFacade::streamRows($path) as $lineNumber => $dataLine) {
                $fields = $dataLine->getFields();
                $data = [];
                foreach ($columns as $i => $col) {
                    if ($col === null) {
                        continue;
                    }
                    $rawValue = isset($fields[$i]) ? $fields[$i]->getValue() : '';
                    $value = trim($rawValue);
                    if ($value === '') {
                        continue;
                    }
                    $data[$col] = $this->castValue($col, $value);
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
                } catch (Throwable $e) {
                    $errors[] = sprintf('Zeile %d: %s', $lineNumber, $e->getMessage());
                    $skipped++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  list<string>  $headerRow
     * @return array<int, string|null>
     */
    private function mapHeaders(array $headerRow): array
    {
        $out = [];
        foreach ($headerRow as $i => $h) {
            $key = StringFacade::isNullOrEmpty($h) ? '' : mb_strtolower(trim($h));
            $out[$i] = self::HEADER_MAP[$key] ?? null;
        }

        return $out;
    }

    private function castValue(string $col, string $val): mixed
    {
        return match ($col) {
            'billable' => in_array(mb_strtolower($val), ['1', 'ja', 'yes', 'true', 'wahr'], true),
            'hourly_rate', 'internal_rate' => NumberFacade::parseDecimal($val, CountryCode::Germany),
            'country', 'currency' => mb_strtoupper($val),
            default => $val,
        };
    }
}
