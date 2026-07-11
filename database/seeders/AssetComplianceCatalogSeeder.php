<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceCatalogSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AssetCompliance\{AssetComplianceNormReference, AssetComplianceProfile};
use Illuminate\Database\Seeder;

/**
 * Feature 075 (MVP-283/292/293, P1): globale Prüfprofil-Vorlagen und die
 * Normen-Referenzmatrix als Katalogdaten (organization_id NULL). Intervalle
 * sind BETRIEBLICHE Vorschlagswerte — welche Pflicht im Einzelfall gilt,
 * entscheidet der Betrieb (keine Rechtsberatung, W12). Regelwerksänderungen
 * sind Datenpflege (frame_version), kein Release.
 */
class AssetComplianceCatalogSeeder extends Seeder {
    public const FRAME_VERSION = '2026-07';

    public function run(): void {
        $profiles = [
            ['code' => 'dguv_v3_portable', 'name' => 'Elektrische Betriebsmittel (ortsveränderlich)', 'inspection_kind' => 'electrical', 'interval_months' => 12, 'warn_days_before' => 30, 'grace_days' => 30, 'blocking_mode' => 'block_after_grace', 'requires_certificate' => false, 'description' => 'Wiederholungsprüfung ortsveränderlicher elektrischer Betriebsmittel (DGUV Vorschrift 3 / TRBS 1201; Richtwert).'],
            ['code' => 'uvv_general', 'name' => 'UVV-/DGUV-Prüfung Arbeitsmittel', 'inspection_kind' => 'dguv_uvv', 'interval_months' => 12, 'warn_days_before' => 60, 'grace_days' => 0, 'blocking_mode' => 'block_immediately', 'requires_certificate' => false, 'description' => 'Jährliche Prüfung von Arbeitsmitteln durch befähigte Person (BetrSichV/DGUV; Richtwert).'],
            ['code' => 'hu_vehicle', 'name' => 'Hauptuntersuchung Kfz', 'inspection_kind' => 'hu_au', 'interval_months' => 24, 'warn_days_before' => 60, 'grace_days' => 0, 'blocking_mode' => 'block_immediately', 'requires_certificate' => true, 'description' => 'HU nach § 29 StVZO (Pkw-Regelfall 24 Monate).'],
            ['code' => 'calibration_annual', 'name' => 'Kalibrierung Messmittel (jährlich)', 'inspection_kind' => 'calibration', 'interval_months' => 12, 'warn_days_before' => 30, 'grace_days' => 14, 'blocking_mode' => 'block_after_grace', 'requires_certificate' => true, 'description' => 'Kalibrierung prüfpflichtiger Messmittel mit Zertifikat (ISO/IEC 17025-Prüfstelle empfohlen).'],
            ['code' => 'verification_scales', 'name' => 'Eichung Waagen', 'inspection_kind' => 'verification', 'interval_months' => 24, 'warn_days_before' => 90, 'grace_days' => 0, 'blocking_mode' => 'block_immediately', 'requires_certificate' => true, 'description' => 'Eichfrist nicht-selbsttätiger Waagen (MessEG/MessEV; Regelfall 2 Jahre).'],
            ['code' => 'manufacturer_service', 'name' => 'Herstellerwartung', 'inspection_kind' => 'manufacturer_service', 'interval_months' => 12, 'warn_days_before' => 30, 'grace_days' => 60, 'blocking_mode' => 'warn', 'requires_certificate' => false, 'description' => 'Wartung nach Herstellervorgabe (Gewährleistungs-/Garantieerhalt).'],
            ['code' => 'internal_function_check', 'name' => 'Interne Funktionsprüfung', 'inspection_kind' => 'function_check', 'interval_months' => 6, 'warn_days_before' => 14, 'grace_days' => 30, 'blocking_mode' => 'warn', 'requires_certificate' => false, 'description' => 'Interne Sicht-/Funktionskontrolle durch eingewiesene Beschäftigte.'],
        ];

        foreach ($profiles as $profile) {
            AssetComplianceProfile::query()->updateOrCreate(
                ['organization_id' => null, 'code' => $profile['code']],
                array_merge($profile, ['frame_version' => self::FRAME_VERSION, 'is_active' => true]),
            );
        }

        // MVP-293: Referenzmatrix — Quellen ohne Konformitätszusage (W12).
        $norms = [
            ['inspection_kind' => 'electrical', 'jurisdiction' => 'DE', 'norm_label' => 'DGUV Vorschrift 3 / TRBS 1201', 'source_url' => 'https://www.dguv.de'],
            ['inspection_kind' => 'dguv_uvv', 'jurisdiction' => 'DE', 'norm_label' => 'BetrSichV / DGUV-Regelwerk', 'source_url' => 'https://www.baua.de'],
            ['inspection_kind' => 'hu_au', 'jurisdiction' => 'DE', 'norm_label' => '§ 29 StVZO (HU/AU)', 'source_url' => 'https://www.gesetze-im-internet.de/stvzo_2012/__29.html'],
            ['inspection_kind' => 'verification', 'jurisdiction' => 'DE', 'norm_label' => 'MessEG / MessEV (Eichfristen)', 'source_url' => 'https://www.gesetze-im-internet.de/messeg/'],
            ['inspection_kind' => 'calibration', 'jurisdiction' => 'DE', 'norm_label' => 'ISO/IEC 17025 (DAkkS-Kalibrierstellen)', 'source_url' => 'https://www.dakks.de'],
        ];

        foreach ($norms as $norm) {
            AssetComplianceNormReference::query()->updateOrCreate(
                ['organization_id' => null, 'inspection_kind' => $norm['inspection_kind'], 'jurisdiction' => $norm['jurisdiction'], 'norm_label' => $norm['norm_label']],
                array_merge($norm, ['frame_version' => self::FRAME_VERSION, 'valid_from' => '2026-01-01']),
            );
        }
    }
}
