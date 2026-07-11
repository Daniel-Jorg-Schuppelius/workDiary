<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxRulesSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxRule;
use Illuminate\Database\Seeder;

/**
 * Phase 23 (MVP-238/244): ausgelieferter Steuerkatalog (org NULL) —
 * DE VOLLSTÄNDIG inkl. historischer Covid-Absenkung (Stichtagstests!),
 * AT/CH nach W1-Check (AT-4,9-%-Eintrag mit dokumentiert offener
 * Fundstelle), weitere Länder rein datengetrieben nachziehbar.
 */
class TaxRulesSeeder extends Seeder {
    public function run(): void {
        $rules = [
            // Deutschland — Standard (services deckt per Resolver-Fallback alle Kategorien).
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'standard', 'rate' => '19.00', 'valid_from' => '2007-01-01', 'valid_to' => '2020-06-30', 'source' => '§ 12 Abs. 1 UStG'],
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'standard', 'rate' => '16.00', 'valid_from' => '2020-07-01', 'valid_to' => '2020-12-31', 'source' => 'Zweites Corona-Steuerhilfegesetz'],
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'standard', 'rate' => '19.00', 'valid_from' => '2021-01-01', 'valid_to' => null, 'source' => '§ 12 Abs. 1 UStG'],
            // Deutschland — ermäßigt.
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'reduced', 'rate' => '7.00', 'valid_from' => '2007-01-01', 'valid_to' => '2020-06-30', 'source' => '§ 12 Abs. 2 UStG'],
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'reduced', 'rate' => '5.00', 'valid_from' => '2020-07-01', 'valid_to' => '2020-12-31', 'source' => 'Zweites Corona-Steuerhilfegesetz'],
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'reduced', 'rate' => '7.00', 'valid_from' => '2021-01-01', 'valid_to' => null, 'source' => '§ 12 Abs. 2 UStG'],
            // Deutschland — RC/Export-Hinweise (Satz 0, Pflichttexte).
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'reverse_charge', 'rate' => '0.00', 'valid_from' => '2007-01-01', 'valid_to' => null, 'source' => '§ 13b UStG / Art. 196 MwStSystRL', 'note' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge, §13b UStG / Art. 196 MwStSystRL).'],
            ['country' => 'DE', 'category' => 'services', 'rate_type' => 'export', 'rate' => '0.00', 'valid_from' => '2007-01-01', 'valid_to' => null, 'source' => '§ 3a UStG', 'note' => 'Nicht im Inland steuerbare Leistung (Leistungsort beim Empfänger, §3a UStG).'],

            // Österreich (W1-Check 2026-07: 4,9 % ab 01.07.2026 per WKO
            // bestätigt, Gesetzesfundstelle offen — Kategorie media).
            ['country' => 'AT', 'category' => 'services', 'rate_type' => 'standard', 'rate' => '20.00', 'valid_from' => '1984-01-01', 'valid_to' => null, 'source' => '§ 10 Abs. 1 öUStG'],
            ['country' => 'AT', 'category' => 'services', 'rate_type' => 'reduced', 'rate' => '10.00', 'valid_from' => '1984-01-01', 'valid_to' => null, 'source' => '§ 10 Abs. 2 öUStG'],
            ['country' => 'AT', 'category' => 'media', 'rate_type' => 'reduced', 'rate' => '10.00', 'valid_from' => '1984-01-01', 'valid_to' => '2026-06-30', 'source' => '§ 10 Abs. 2 öUStG'],
            ['country' => 'AT', 'category' => 'media', 'rate_type' => 'reduced', 'rate' => '4.90', 'valid_from' => '2026-07-01', 'valid_to' => null, 'source' => 'WKO-Bestätigung 2026 (W1); Gesetzesfundstelle offen — vor Erstnutzung prüfen'],

            // Schweiz (Sätze seit 01.01.2024).
            ['country' => 'CH', 'category' => 'services', 'rate_type' => 'standard', 'rate' => '8.10', 'valid_from' => '2024-01-01', 'valid_to' => null, 'source' => 'Art. 25 Abs. 1 MWSTG'],
            ['country' => 'CH', 'category' => 'services', 'rate_type' => 'reduced', 'rate' => '2.60', 'valid_from' => '2024-01-01', 'valid_to' => null, 'source' => 'Art. 25 Abs. 2 MWSTG'],
        ];

        foreach ($rules as $rule) {
            TaxRule::query()->firstOrCreate([
                'organization_id' => null,
                'country' => $rule['country'],
                'category' => $rule['category'],
                'rate_type' => $rule['rate_type'],
                'valid_from' => $rule['valid_from'],
            ], [
                'region' => null,
                'rate' => $rule['rate'],
                'valid_to' => $rule['valid_to'],
                'source' => $rule['source'],
                'note' => $rule['note'] ?? null,
                'status' => 'active',
            ]);
        }
    }
}
