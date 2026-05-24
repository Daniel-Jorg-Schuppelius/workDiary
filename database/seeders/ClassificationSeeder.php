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
 * Quelle: docs/kernklassifikationen.md §2.
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
        ];
    }
}
