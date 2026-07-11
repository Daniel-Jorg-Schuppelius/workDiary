<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityDefaultsSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sustainability\{SustainabilityFactorSet, SustainabilityFrameMapping};
use Illuminate\Database\Seeder;

/**
 * Feature 071: ausgelieferte Standard-Daten —
 * (1) globales Emissionsfaktoren-Set (UBA-/DEFRA-basiert, frei; Werte
 *     gerundete Standardwerte, Quelle je Faktor), org-überschreibbar;
 * (2) VSME-1.0-Referenzmatrix (D8: VSME-first; esrs-2.0/iso14001-2026
 *     folgen erst nach den Watchlist-Checks W4/W6).
 */
class SustainabilityDefaultsSeeder extends Seeder {
    public function run(): void {
        $set = SustainabilityFactorSet::query()->firstOrCreate(
            ['organization_id' => null, 'name' => 'UBA/DEFRA Standard', 'year' => 2026],
            ['source' => 'UBA-Emissionsfaktorenliste 2026 / DEFRA-DESNZ Conversion Factors 2026 (OGL v3)', 'region' => 'DE', 'active' => true],
        );

        $factors = [
            ['activity_code' => 'electricity_kwh', 'label' => 'Strom (DE-Mix)', 'unit_code' => 'kg_co2e_per_kwh', 'factor' => '0.344000', 'scope' => 2, 'source_note' => 'UBA Strommix DE 2025'],
            ['activity_code' => 'heat_kwh', 'label' => 'Fernwärme', 'unit_code' => 'kg_co2e_per_kwh', 'factor' => '0.180000', 'scope' => 2, 'source_note' => 'UBA Fernwärme (Standardwert)'],
            ['activity_code' => 'gas_kwh', 'label' => 'Erdgas', 'unit_code' => 'kg_co2e_per_kwh', 'factor' => '0.201000', 'scope' => 1, 'source_note' => 'UBA Erdgas Brennwert'],
            ['activity_code' => 'diesel_l', 'label' => 'Diesel', 'unit_code' => 'kg_co2e_per_l', 'factor' => '2.660000', 'scope' => 1, 'source_note' => 'DEFRA 2026 Diesel (avg biofuel blend)'],
            ['activity_code' => 'petrol_l', 'label' => 'Benzin', 'unit_code' => 'kg_co2e_per_l', 'factor' => '2.330000', 'scope' => 1, 'source_note' => 'DEFRA 2026 Petrol (avg biofuel blend)'],
            ['activity_code' => 'km_car', 'label' => 'Pkw-Kilometer (Durchschnitt)', 'unit_code' => 'kg_co2e_per_km', 'factor' => '0.170000', 'scope' => 1, 'source_note' => 'DEFRA 2026 average car'],
            ['activity_code' => 'km_truck', 'label' => 'Lkw-Kilometer (leicht)', 'unit_code' => 'kg_co2e_per_km', 'factor' => '0.250000', 'scope' => 1, 'source_note' => 'DEFRA 2026 light commercial vehicle'],
            ['activity_code' => 'waste_kg', 'label' => 'Restabfall (Verbrennung)', 'unit_code' => 'kg_co2e_per_kg', 'factor' => '0.450000', 'scope' => 3, 'source_note' => 'DEFRA 2026 waste (residual)'],
            ['activity_code' => 'water_m3', 'label' => 'Trinkwasser inkl. Abwasser', 'unit_code' => 'kg_co2e_per_m3', 'factor' => '0.700000', 'scope' => 3, 'source_note' => 'DEFRA 2026 water supply+treatment'],
        ];
        foreach ($factors as $factor) {
            $set->factors()->firstOrCreate(
                ['activity_code' => $factor['activity_code'], 'valid_from' => '2026-01-01'],
                [...$factor, 'valid_to' => null, 'quality' => 'high'],
            );
        }

        $sections = [
            ['B1', 'Grundlagen und Berichtsbasis', 'Organisationsprofil, Standorte (Sites/Buildings), Berichtszeitraum'],
            ['B2', 'Praktiken und Richtlinien', 'Prozeduren/Arbeitsanweisungen, Managementsysteme (ISMS/Arbeitsschutz)'],
            ['B3', 'Energie und THG-Emissionen', 'Aktivitätsdaten (Strom/Wärme/Kraftstoff) + Faktorbibliothek, Scope 1/2'],
            ['B4', 'Verschmutzung', 'Gefahrstoff-Kriterien der Bewertungen; Nachweise im DMS'],
            ['B5', 'Biodiversität', 'Standortbezug (Sites) — manuelle Angaben'],
            ['B6', 'Wasser', 'Aktivitätsdaten water_m3'],
            ['B7', 'Abfall und Kreislaufwirtschaft', 'Aktivitätsdaten waste_kg, Reparierbarkeits-/Wiederverwendungs-Kriterien'],
            ['B8', 'Belegschaft: Merkmale', 'Mitgliederverwaltung (aggregiert, keine Personendaten im Bericht)'],
            ['B9', 'Belegschaft: Gesundheit und Sicherheit', 'Arbeitsschutzereignisse (SafetyEvents), Unterweisungen'],
            ['B10', 'Belegschaft: Vergütung und Weiterbildung', 'Lohn-/Qualifikationsmodule (aggregiert)'],
            ['B11', 'Verurteilungen/Korruption', 'Governance-Kriterien + Compliance-Module'],
            ['C1', 'Strategie und Geschäftsmodell', 'Managementbewertungs-Snapshot'],
            ['C2', 'Wesentliche Nachhaltigkeitsthemen', 'Kritische Bewertungen (rote Ampeln) + Maßnahmenregister'],
            ['C3', 'THG-Reduktionsziele', 'Zielpfade (co2e_total) mit Basis-/Zieljahr'],
            ['C4', 'Klimarisiken', 'Risiko-Notizen der Bewertungen; ISMS-/Krisenmodul'],
            ['C5', 'Belegschaft: zusätzliche Angaben', 'HR-Kennzahlen (aggregiert)'],
            ['C6', 'Menschenrechte: Richtlinien', 'Governance-Kriterien, Lieferanten-Nachweise'],
            ['C7', 'Menschenrechte: Vorfälle', 'Hinweisgebersystem (aggregiert, anonym)'],
            ['C8', 'Umsatz nach Sektoren', 'Faktura-/Reporting-Module'],
            ['C9', 'Governance: Diversität Leitungsorgan', 'manuelle Angabe'],
        ];
        foreach ($sections as [$code, $label, $note]) {
            SustainabilityFrameMapping::query()->firstOrCreate(
                ['organization_id' => null, 'frame' => 'vsme', 'frame_version' => '1.0', 'section_code' => $code],
                ['section_label' => $label, 'mapping_note' => $note, 'active' => true],
            );
        }
    }
}
