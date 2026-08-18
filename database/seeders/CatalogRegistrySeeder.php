<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogRegistrySeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\Catalog\{CatalogCodeMapping, CatalogEntry, CatalogRegistry};
use Illuminate\Database\Seeder;

/**
 * Katalogstamm (Feature 109, MVP-637): DIN-276-Kostengruppen der Ausgaben 2018
 * und 2008 sowie die StLB-Bau-Leistungsbereiche.
 *
 * **Der Seeder ist idempotent und darf jeden Deploy laufen** — er legt an und
 * gleicht Bezeichnungen ab, löscht aber nie: Eine Zuordnung, die auf einen
 * Schlüssel zeigt, verlöre sonst ihren Bezug.
 *
 * Beide DIN-Ausgaben stehen **nebeneinander** (D3). Ein Vorhaben, das nach 2008
 * abrechnet, rechnet weiter danach ab; die Zuordnungstabelle zwischen den
 * Ausgaben ist ein **Vorschlag**, keine Migration — deshalb liegt sie in
 * `catalog_code_mappings` und wird nirgends automatisch angewandt.
 *
 * Ausgeliefert werden ausschließlich Nummern und Kurzbezeichnungen (D6).
 */
class CatalogRegistrySeeder extends Seeder {
    public function run(): void {
        $din2018 = $this->registry([
            'key' => 'din276-2018',
            'kind' => CatalogRegistry::KIND_COST_GROUP,
            'name' => 'DIN 276 Kostengruppen',
            'edition' => '2018-12',
            // Unter diesem Typ tritt der Katalog in GAEB-Dateien auf; damit
            // ordnet ein Import ihn ohne Raten zu.
            'gaeb_type' => 'cost group DIN 276 2018-12',
            'levels' => 3,
        ]);
        $this->entries($din2018, require database_path('data/catalogs/din276-2018.php'));

        $din2008 = $this->registry([
            'key' => 'din276-2008',
            'kind' => CatalogRegistry::KIND_COST_GROUP,
            'name' => 'DIN 276-1 Kostengruppen',
            'edition' => '2008-12',
            'gaeb_type' => 'cost group DIN 276-1 2008-12',
            'levels' => 2,
        ]);
        $this->entries($din2008, require database_path('data/catalogs/din276-2008.php'));

        $stlb = $this->registry([
            'key' => 'stlb-lb',
            'kind' => CatalogRegistry::KIND_WORK_CATEGORY,
            'name' => 'Leistungsbereiche (StLB-Bau)',
            'edition' => null,
            'gaeb_type' => 'work category',
            'levels' => 1,
        ]);
        $this->workCategories($stlb, require database_path('data/catalogs/stlb-leistungsbereiche.php'));

        $this->mappings($din2008, $din2018);
    }

    /** @param array<string, mixed> $attributes */
    private function registry(array $attributes): CatalogRegistry {
        /** @var CatalogRegistry $registry */
        $registry = CatalogRegistry::query()->firstOrNew([
            'organization_id' => null,
            'key' => $attributes['key'],
        ]);
        $registry->fill($attributes + ['active' => true]);
        $registry->save();

        return $registry;
    }

    /**
     * Kostengruppen: Die Ebene steckt in der Nummer — „310" ist die zweite,
     * „311" die dritte Ebene unter „300".
     *
     * @param list<array{0: string, 1: int, 2: string, 3: string, 4: string, 5: string, 6: string}> $rows
     */
    private function entries(CatalogRegistry $registry, array $rows): void {
        foreach ($rows as $position => [$code, $level, $de, $en, $fr, $it, $es]) {
            CatalogEntry::query()->updateOrCreate(
                ['catalog_registry_id' => $registry->id, 'code' => $code],
                [
                    'label' => $de,
                    'labels' => ['en' => $en, 'fr' => $fr, 'it' => $it, 'es' => $es],
                    'level' => $level,
                    'parent_code' => $this->parentCode($code, $level),
                    'position' => $position,
                ],
            );
        }
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> $rows
     */
    private function workCategories(CatalogRegistry $registry, array $rows): void {
        foreach ($rows as $position => [$code, $de, $en, $fr, $it, $es]) {
            CatalogEntry::query()->updateOrCreate(
                ['catalog_registry_id' => $registry->id, 'code' => $code],
                [
                    'label' => $de,
                    'labels' => ['en' => $en, 'fr' => $fr, 'it' => $it, 'es' => $es],
                    'level' => 1,
                    'parent_code' => null,
                    'position' => $position,
                ],
            );
        }
    }

    /** „311" hängt unter „310", „310" unter „300", „300" hat keinen Elternteil. */
    private function parentCode(string $code, int $level): ?string {
        return match ($level) {
            3 => substr($code, 0, 2) . '0',
            2 => substr($code, 0, 1) . '00',
            default => null,
        };
    }

    /**
     * Zuordnung 2008 → 2018 für die Schlüssel, die sich **eindeutig**
     * entsprechen.
     *
     * Bewusst lückenhaft: Wo die Ausgabe 2018 neu gegliedert hat (200er, 500er,
     * 600/700), gibt es keine 1:1-Entsprechung. Eine Zeile zu erfinden hieße,
     * eine fachliche Entscheidung zu simulieren — die Lücke ist die ehrlichere
     * Auskunft.
     */
    private function mappings(CatalogRegistry $from, CatalogRegistry $to): void {
        $pairs = [
            ['100', '100', null], ['110', '110', null], ['120', '120', null],
            ['300', '300', null], ['310', '310', null], ['320', '320', null],
            ['330', '330', null], ['340', '340', null], ['350', '350', null],
            ['360', '360', null],
            ['370', '380', 'Baukonstruktive Einbauten: 2018 unter 380.'],
            ['390', '390', null],
            ['400', '400', null], ['410', '410', null], ['420', '420', null],
            ['430', '430', 'Lufttechnische Anlagen heißen 2018 raumlufttechnische Anlagen.'],
            ['440', '440', 'Starkstromanlagen heißen 2018 elektrische Anlagen.'],
            ['450', '450', null], ['460', '460', null], ['470', '470', null],
            ['480', '480', null], ['490', '490', null],
            ['700', '700', null], ['710', '710', null], ['720', '720', null],
            ['750', '750', null], ['770', '760', 'Allgemeine Baunebenkosten: 2018 unter 760.'],
            ['790', '790', null],
        ];

        foreach ($pairs as [$fromCode, $toCode, $note]) {
            CatalogCodeMapping::query()->updateOrCreate(
                ['from_registry_id' => $from->id, 'to_registry_id' => $to->id, 'from_code' => $fromCode],
                ['to_code' => $toCode, 'note' => $note],
            );
        }
    }
}
