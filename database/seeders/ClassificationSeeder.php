<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification;
use Illuminate\Database\Seeder;

/**
 * Seedet Plattform-Defaults (organization_id = NULL) für alle Kern-Domänen.
 *
 * Quelle: ../WorkDiary-Architecture/kernklassifikationen.md §2.
 */
class ClassificationSeeder extends Seeder {
    public function run(): void {
        $sort = 0;
        foreach (self::defaults() as $domain => $entries) {
            foreach ($entries as $code => $label) {
                $sort += 10;
                Classification::query()->updateOrCreate(
                    [
                        'organization_id' => null,
                        'domain' => $domain,
                        'code' => $code,
                    ],
                    [
                        'label' => $label,
                        'sort_order' => $sort,
                        'active' => true,
                    ],
                );
            }
            $sort = 0;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function defaults(): array {
        return [
            ClassificationDomain::EntryType->value => [
                'service' => 'Service',
                'maintenance' => 'Wartung',
                'installation' => 'Installation',
                'repair' => 'Reparatur',
                'advice' => 'Beratung',
            ],
            ClassificationDomain::Activity->value => [
                'analysis' => 'Analyse',
                'install' => 'Installation',
                'configure' => 'Konfiguration',
                'repair' => 'Reparatur',
                'document' => 'Dokumentation',
            ],
            ClassificationDomain::DefectType->value => [
                'hardware' => 'Hardware',
                'software' => 'Software',
                'wiring' => 'Verkabelung',
                'mechanical' => 'Mechanik',
                'user' => 'Bedienung',
                'env' => 'Umgebung',
            ],
            ClassificationDomain::RootCause->value => [
                'wear' => 'Verschleiß',
                'misuse' => 'Fehlbedienung',
                'defect' => 'Defekt',
                'configuration' => 'Konfiguration',
                'external' => 'Externe Ursache',
            ],
            ClassificationDomain::Result->value => [
                'resolved' => 'Behoben',
                'workaround' => 'Workaround',
                'openIssue' => 'Offener Punkt',
                'escalated' => 'Eskaliert',
            ],
            ClassificationDomain::Priority->value => [
                'low' => 'Niedrig',
                'normal' => 'Normal',
                'high' => 'Hoch',
                'critical' => 'Kritisch',
            ],
            ClassificationDomain::GoodwillReason->value => [
                'warranty' => 'Garantie',
                'customerRelation' => 'Kundenbeziehung',
                'escalation' => 'Eskalation',
                'error' => 'Eigener Fehler',
            ],
            ClassificationDomain::ReworkReason->value => [
                'qualityIssue' => 'Qualitätsmangel',
                'missingPart' => 'Fehlendes Teil',
                'additionalScope' => 'Zusatzleistung',
            ],
            ClassificationDomain::ProductGroup->value => [
                'router' => 'Router',
                'switch' => 'Switch',
                'server' => 'Server',
                'hvac' => 'HLK',
                'lighting' => 'Beleuchtung',
            ],
            ClassificationDomain::DienstmittelType->value => [
                'tool' => 'Werkzeug',
                'vehicle' => 'Fahrzeug',
                'instrument' => 'Messgerät',
                'device' => 'Gerät',
            ],
            // 14 Hauptallergene nach LMIV (EU 1169/2011, Anhang II) — universelle
            // gesetzliche Liste, daher als Plattform-Default.
            ClassificationDomain::Allergen->value => [
                'gluten' => 'Glutenhaltiges Getreide',
                'krebstiere' => 'Krebstiere',
                'ei' => 'Eier',
                'fisch' => 'Fische',
                'erdnuss' => 'Erdnüsse',
                'soja' => 'Soja',
                'milch' => 'Milch / Laktose',
                'schalenfruechte' => 'Schalenfrüchte (Nüsse)',
                'sellerie' => 'Sellerie',
                'senf' => 'Senf',
                'sesam' => 'Sesam',
                'sulfite' => 'Schwefeldioxid / Sulfite',
                'lupine' => 'Lupinen',
                'weichtiere' => 'Weichtiere',
            ],
            // Gewerke (Dienstleister-Kategorien).
            ClassificationDomain::Trade->value => [
                'catering' => 'Catering',
                'technik' => 'Veranstaltungstechnik',
                'security' => 'Security',
                'buehne' => 'Bühne / Rigging',
                'transport' => 'Transport / Logistik',
                'reinigung' => 'Reinigung',
            ],
            // AVV-Abfallschlüssel (Feature 100): gängige Schlüssel für
            // Elektro-Altgeräte, Datenträger, Batterien und Toner nach der
            // Abfallverzeichnis-Verordnung — universelle gesetzliche Liste
            // (analog Allergene), daher als Plattform-Default. Label-Format:
            // „<AVV-Schlüssel> — <Beschreibung>"; Stern * = gefährlich.
            ClassificationDomain::WasteCode->value => [
                'avv_160211_h' => '16 02 11* — Geräte mit FCKW/HFCKW/HFKW',
                'avv_160213_h' => '16 02 13* — Geräte mit gefährlichen Bauteilen',
                'avv_160214' => '16 02 14 — Geräte ohne gefährliche Bauteile',
                'avv_160215_h' => '16 02 15* — Entnommene gefährliche Bauteile',
                'avv_160216' => '16 02 16 — Entnommene Bauteile (nicht gefährlich)',
                'avv_160601_h' => '16 06 01* — Bleibatterien',
                'avv_160602_h' => '16 06 02* — Ni-Cd-Batterien',
                'avv_160604' => '16 06 04 — Alkalibatterien',
                'avv_160605' => '16 06 05 — Andere Batterien und Akkumulatoren',
                'avv_200121_h' => '20 01 21* — Leuchtstoffröhren und quecksilberhaltige Abfälle',
                'avv_200123_h' => '20 01 23* — Gebrauchte FCKW-haltige Geräte',
                'avv_200135_h' => '20 01 35* — Elektro-Altgeräte mit gefährlichen Bauteilen',
                'avv_200136' => '20 01 36 — Elektro-Altgeräte ohne gefährliche Bauteile',
                'avv_080317_h' => '08 03 17* — Tonerabfälle mit gefährlichen Bestandteilen',
                'avv_080318' => '08 03 18 — Tonerabfälle (nicht gefährlich)',
            ],
            // Genehmigungsarten (Behördliche Genehmigungen).
            ClassificationDomain::PermitType->value => [
                'sondernutzung' => 'Sondernutzung öffentl. Raum',
                'sperrzeit' => 'Sperrzeitverkürzung',
                'gema' => 'GEMA-Anmeldung',
                'schankerlaubnis' => 'Schankerlaubnis',
                'sicherheitskonzept' => 'Sicherheitskonzept',
                'brandschutz' => 'Brandschutz',
                'laermschutz' => 'Lärmschutz / Ausnahme',
                'lebensmittel' => 'Lebensmittel / Gaststätte',
            ],
        ];
    }
}
