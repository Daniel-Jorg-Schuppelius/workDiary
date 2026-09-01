<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpCenterCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

use App\Models\HelpTopic;
use Illuminate\Support\{Collection, Str};

/**
 * Themenbereiche des Hilfecenters (Feature 039, MVP-752): zentrale,
 * testbare Zuordnung Topic-Code → Bereich über config/help-center.php.
 * Matching wie in der Route→Topic-Registry: Muster via Str::is in
 * Config-Reihenfolge, der ERSTE Treffer gewinnt; ohne Treffer fällt das
 * Topic in den Auffangbereich (Gate: HelpCenterCatalogTest).
 */
class HelpCenterCatalog {
    public const FALLBACK_KEY = 'weitere';

    /**
     * Bereichsdefinitionen in Anzeige-Reihenfolge (ohne Topics).
     *
     * @return list<array{key:string, icon:string, title:string, description:string}>
     */
    public function sections(): array {
        $sections = [];
        foreach ($this->definitions() as $key => $definition) {
            $sections[] = [
                'key' => $key,
                'icon' => (string) ($definition['icon'] ?? 'help'),
                'title' => __('help.sections.' . $key . '.title'),
                'description' => __('help.sections.' . $key . '.description'),
            ];
        }

        $sections[] = [
            'key' => self::FALLBACK_KEY,
            'icon' => 'more_horiz',
            'title' => __('help.sections.' . self::FALLBACK_KEY . '.title'),
            'description' => __('help.sections.' . self::FALLBACK_KEY . '.description'),
        ];

        return $sections;
    }

    /** Bereichs-Key eines Topic-Codes (erster Muster-Treffer, sonst Auffang). */
    public function sectionKeyFor(string $topic): string {
        foreach ($this->definitions() as $key => $definition) {
            foreach ((array) ($definition['patterns'] ?? []) as $pattern) {
                if ($pattern !== '' && Str::is($pattern, $topic)) {
                    return $key;
                }
            }
        }

        return self::FALLBACK_KEY;
    }

    /**
     * Gruppiert sichtbare Topics nach Bereichs-Key (auch leere Bereiche
     * fehlen im Ergebnis — die View blendet sie aus).
     *
     * @param Collection<int, HelpTopic> $topics
     * @return array<string, Collection<int, HelpTopic>>
     */
    public function grouped(Collection $topics): array {
        return $topics
            ->groupBy(fn(HelpTopic $row): string => $this->sectionKeyFor($row->topic))
            ->map(fn(Collection $rows) => $rows->sortBy('title')->values())
            ->all();
    }

    /**
     * Topics, die unabgesichert im Auffangbereich landen würden — Grundlage
     * des Zuordnungs-Gates. Bewusste Ausnahmen stehen in
     * config('help-center.fallback_allowed').
     *
     * @param list<string> $topicCodes
     * @return list<string>
     */
    public function unassigned(array $topicCodes): array {
        /** @var list<string> $allowed */
        $allowed = (array) config('help-center.fallback_allowed', []);

        $unassigned = [];
        foreach ($topicCodes as $code) {
            if ($this->sectionKeyFor($code) === self::FALLBACK_KEY && ! in_array($code, $allowed, true)) {
                $unassigned[] = $code;
            }
        }

        return $unassigned;
    }

    /** @return array<string, array{icon?:string, patterns?:list<string>}> */
    private function definitions(): array {
        /** @var array<string, array{icon?:string, patterns?:list<string>}> $sections */
        $sections = (array) config('help-center.sections', []);

        return $sections;
    }
}
