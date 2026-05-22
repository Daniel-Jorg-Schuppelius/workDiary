<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemForeignRateSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\PerDiemRate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seedet die Auslandsverpflegungs- und Übernachtungspauschalen nach BMF.
 *
 * Datenquelle: database/data/per-diem-foreign-2025.json (Auszug der wichtigsten Destinationen
 * aus dem BMF-Schreiben vom 02.12.2024, gültig ab 01.01.2025). Pflege erfolgt über die JSON-
 * Datei, dieser Seeder ist idempotent (composite key: country + region_label + valid_from).
 */
class PerDiemForeignRateSeeder extends Seeder {
    public function run(): void {
        $path = database_path('data/per-diem-foreign-2025.json');

        if (!is_file($path)) {
            throw new RuntimeException("Per-Diem JSON nicht gefunden: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Per-Diem JSON konnte nicht gelesen werden: {$path}");
        }

        $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        $meta = $payload['_meta'] ?? [];
        $rates = $payload['rates'] ?? [];

        $validFrom = CarbonImmutable::parse($meta['valid_from'] ?? '2025-01-01')->startOfDay();
        $validToRaw = $meta['valid_to'] ?? null;
        $validTo = $validToRaw !== null ? CarbonImmutable::parse($validToRaw)->startOfDay() : null;
        $currency = strtoupper((string) ($meta['currency'] ?? 'EUR'));
        $source = mb_substr((string) ($meta['source_short'] ?? $meta['source'] ?? 'BMF Auslandsreisekosten'), 0, 100);

        foreach ($rates as $row) {
            $country = strtoupper(trim((string) ($row['country'] ?? '')));
            if ($country === '') {
                continue;
            }

            $region = $row['region'] ?? null;
            if (is_string($region)) {
                $region = trim($region);
                if ($region === '') {
                    $region = null;
                }
            }

            PerDiemRate::query()->updateOrCreate(
                [
                    'country' => $country,
                    'region_label' => $region,
                    'valid_from' => $validFrom,
                ],
                [
                    'valid_to' => $validTo,
                    'full_day_amount' => number_format((float) ($row['full_day'] ?? 0), 2, '.', ''),
                    'partial_day_amount' => number_format((float) ($row['partial_day'] ?? 0), 2, '.', ''),
                    'overnight_amount' => isset($row['overnight'])
                        ? number_format((float) $row['overnight'], 2, '.', '')
                        : null,
                    'currency' => $currency,
                    'source' => $source,
                ]
            );
        }
    }
}
