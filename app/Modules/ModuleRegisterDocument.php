<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleRegisterDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use Illuminate\Support\Str;

/**
 * Modulregister als Markdown (MVP-873) für `WorkDiary-Architecture/modul-register.md`.
 * Deterministisch (keine Zeitstempel, feste Sortierung, deutsche Labels),
 * damit `modules:check` die Datei gegen den Generatorstand vergleichen kann.
 */
final class ModuleRegisterDocument {
    public const RELATIVE_PATH = '../WorkDiary-Architecture/modul-register.md';

    /** Gates, die den Modulschnitt und die Kernbausteine absichern (Phase 111). */
    private const GATES = [
        'ModuleManifestCoverageRuleTest' => 'jede Tabelle, jeder Ordner, jede Rechtegruppe genau einem Manifest',
        'DomainFolderRuleTest' => 'Klassen in Domänenordnern des Manifests',
        'ModuleBoundaryRuleTest' => 'Kanten nach `BoundaryRules`, Baseline nur schrumpfend',
        'ObserverModuleRuleTest' => 'Observer nur im eigenen Modul oder der Plattform',
        'EventNamingRuleTest' => 'Domain-Events in Vergangenheitsform unter `App\\Events`',
        'MorphMapCoverageRuleTest' => 'jedes Modell mit Morph-Alias',
        'RawMorphClassLiteralRuleTest' => 'keine Klassennamen in Typspalten',
        'JournalContractRuleTest' => 'Journale erben `JournalEntry`, Schreiben über `record()`',
        'DocumentLineContractRuleTest' => 'Menge × Preis nur im `DocumentTotalsCalculator`',
        'FieldSchemaRuleTest' => 'ein Erfassungs-Typkatalog (`FieldType`)',
        'ContactColumnsRuleTest' => 'Anschriften nur in `contact_addresses`',
        'ColumnNamingRuleTest' => 'Spaltennamen nach `datenbank-konventionen.md`',
        'IndexQueryRuleTest' => 'Sortierung nur über `SortableQuery::resolve()`',
        'EnumTransitionContractTest' => 'Übergangstabellen nur im Status-Enum',
    ];

    public function __construct(private readonly ModuleRegistry $registry) {}

    /** @param  array<string, string>  $helpRoutes  Routenmuster → Hilfethema (`config('help-topics.routes')`) */
    public function render(array $helpRoutes): string {
        $previous = app()->getLocale();
        app()->setLocale('de');
        try {
            return $this->build($helpRoutes);
        } finally {
            app()->setLocale($previous);
        }
    }

    /** @param  array<string, string>  $helpRoutes */
    private function build(array $helpRoutes): string {
        $manifests = $this->registry->all();
        usort($manifests, static fn (Manifest $a, Manifest $b): int => [self::kindOrder($a->kind()), $a->code()] <=> [self::kindOrder($b->kind()), $b->code()]);
        $topics = $this->topicsByModule($manifests, $helpRoutes);

        $lines = [
            '# Modulregister',
            '',
            '> Erzeugt von `php artisan modules:doc` aus den Manifesten unter',
            '> `app/Modules/Manifests` (MVP-873) — nicht von Hand bearbeiten;',
            '> `modules:check` meldet eine veraltete Fassung.',
            '',
            sprintf('%d Module, %d Tabellen.', count($manifests), array_sum(array_map(static fn (Manifest $m): int => count($m->tables()), $manifests))),
            '',
            '| Modul | Art | Lizenz | Tabellen | Voraussetzungen |',
            '| --- | --- | --- | --- | --- |',
        ];
        foreach ($manifests as $manifest) {
            $lines[] = sprintf(
                '| [%s](#%s) — %s | %s | %s | %d | %s |',
                $manifest->code(),
                strtolower($manifest->code()),
                $this->cell($manifest->label()),
                $manifest->kind()->label(),
                $manifest->licenseCode() === null ? '—' : '`' . $manifest->licenseCode() . '`' . ($manifest->ownsLicense() ? '' : ' (mit)'),
                count($manifest->tables()),
                $this->codeList($manifest->requires()),
            );
        }

        foreach ($manifests as $manifest) {
            array_push($lines, '', '## ' . $manifest->code(), '', '**' . $this->cell($manifest->label()) . '** · ' . $manifest->kind()->label());
            if (trim($manifest->description()) !== '') {
                array_push($lines, '', trim($manifest->description()));
            }
            $lines[] = '';
            $lines[] = '- Lizenzcode: ' . ($manifest->licenseCode() === null ? '—' : '`' . $manifest->licenseCode() . '`' . ($manifest->ownsLicense() ? ' (Eigentümer)' : ''));
            $lines[] = '- Ordner: ' . $this->codeList($manifest->folders());
            $lines[] = '- Tabellen: ' . $this->codeList($manifest->tables());
            $lines[] = '- Routen: ' . $this->codeList($manifest->routePatterns());
            $lines[] = '- Rechtegruppen: ' . $this->plainList(array_map(static fn (PermissionGroup $g): string => $g->label(), $manifest->permissionGroups()));
            $lines[] = '- Hilfethemen: ' . $this->codeList($topics[$manifest->code()] ?? []);
            $lines[] = '- Voraussetzungen: ' . $this->codeList($manifest->requires());
            $lines[] = '- Plugins: ' . $this->codeList($manifest->plugins());
            $lines[] = '- Erweiterungen: ' . $this->codeList($this->shortNames(array_merge([], ...array_values($manifest->extensions()))));
            $lines[] = '- Contracts: ' . $this->codeList($this->shortNames(array_keys($manifest->contracts())));
            $lines[] = '- Bindungen: ' . $this->codeList($this->shortNames(array_keys($manifest->bindings())));
            $lines[] = '- Listener: ' . $this->codeList($this->shortNames(array_merge([], ...array_values($manifest->listeners()))));
        }

        array_push($lines, '', '## Gates', '', '| Gate | prüft |', '| --- | --- |');
        foreach (self::GATES as $gate => $purpose) {
            $lines[] = sprintf('| `%s` | %s |', $gate, $purpose);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Hilfethema → Modul über das spezifischste Routenmuster.
     *
     * @param  list<Manifest>  $manifests
     * @param  array<string, string>  $helpRoutes
     * @return array<string, list<string>>
     */
    private function topicsByModule(array $manifests, array $helpRoutes): array {
        $patterns = [];
        foreach ($manifests as $manifest) {
            foreach ($manifest->routePatterns() as $pattern) {
                $patterns[$pattern] = $manifest->code();
            }
        }
        uksort($patterns, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));

        $topics = [];
        foreach ($helpRoutes as $route => $topic) {
            $candidate = str_ends_with($route, '*') ? rtrim($route, '*') . 'index' : $route;
            foreach ($patterns as $pattern => $code) {
                if (Str::is($pattern, $candidate)) {
                    $topics[$code][] = $topic;
                    break;
                }
            }
        }

        return array_map(static function (array $list): array {
            $list = array_values(array_unique($list));
            sort($list);

            return $list;
        }, $topics);
    }

    private static function kindOrder(ModuleKind $kind): int {
        return match ($kind) {
            ModuleKind::Platform => 0,
            ModuleKind::Core => 1,
            ModuleKind::Feature => 2,
        };
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    private function shortNames(array $classes): array {
        $names = array_map(static fn (string $class): string => class_basename($class), $classes);
        sort($names);

        return array_values(array_unique($names));
    }

    /** @param  list<string>  $items */
    private function codeList(array $items): string {
        return $items === [] ? '—' : implode(', ', array_map(static fn (string $item): string => '`' . $item . '`', $items));
    }

    /** @param  list<string>  $items */
    private function plainList(array $items): string {
        return $items === [] ? '—' : implode(', ', $items);
    }

    private function cell(string $text): string {
        return str_replace('|', '\|', $text);
    }
}
