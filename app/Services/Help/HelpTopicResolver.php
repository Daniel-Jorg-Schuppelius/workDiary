<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Help;

use App\Models\Platform\{HelpTopic, User};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Support\Facades\App;

class HelpTopicResolver {
    public function __construct(
        private readonly FeatureFlagResolver $features,
    ) {}

    /**
     * Findet ein Topic für die bevorzugte Locale, mit Fallback de→en.
     * Audience-Filter: leere/fehlende audience = sichtbar für alle.
     */
    public function find(string $topic, ?User $user = null, ?string $preferredLocale = null): ?HelpTopic {
        return $this->firstInChain($topic, $preferredLocale, fn(HelpTopic $row): bool => $this->isVisibleFor($row, $user));
    }

    /**
     * Hilfe einer Seite, die der Nutzer geöffnet hat: Der Seitenzugriff ist
     * die Freigabe, die Zielgruppe zählt nicht — das Modul-Gating bleibt.
     */
    public function findForPage(string $topic, ?string $preferredLocale = null): ?HelpTopic {
        return $this->firstInChain($topic, $preferredLocale, fn(HelpTopic $row): bool => $this->modulesEnabled($row));
    }

    /** @param \Closure(HelpTopic): bool $visible */
    private function firstInChain(string $topic, ?string $preferredLocale, \Closure $visible): ?HelpTopic {
        $locales = $this->localeFallbackChain($preferredLocale);

        $candidates = HelpTopic::query()
            ->where('topic', $topic)
            ->whereIn('locale', $locales)
            ->get()
            ->keyBy('locale');

        foreach ($locales as $locale) {
            /** @var HelpTopic|null $row */
            $row = $candidates->get($locale);
            if ($row !== null && $visible($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Verwandte Themen eines Topics — nur existente UND sichtbare Ziele,
     * mit lokalisiertem Titel (kein toter Link, keine rohen Topic-Codes).
     * Gemeinsame Quelle für Drawer-JSON und Hilfecenter-Vollseite (MVP-752).
     *
     * @return list<array{topic:string, title:string}>
     */
    public function relatedFor(HelpTopic $row, ?User $user = null): array {
        return array_values(collect($row->related ?? [])
            ->map(function (string $slug) use ($user): ?array {
                $target = $this->find($slug, $user);

                return $target === null ? null : ['topic' => $target->topic, 'title' => $target->title];
            })
            ->filter()
            ->all());
    }

    /**
     * Alle für den Nutzer sichtbaren Topics der Locale-Kette — je Topic der
     * erste sichtbare Treffer in Kettenreihenfolge, ohne longText-Spalten.
     * Datengrundlage der Bereichsübersicht (MVP-752).
     *
     * @return \Illuminate\Support\Collection<int, HelpTopic>
     */
    public function visibleForLocale(?User $user = null, ?string $preferredLocale = null): \Illuminate\Support\Collection {
        $locales = $this->localeFallbackChain($preferredLocale);
        $order = array_flip($locales);

        return HelpTopic::query()
            ->whereIn('locale', $locales)
            ->get(['id', 'topic', 'locale', 'title', 'audience', 'modules', 'version'])
            ->sortBy(fn(HelpTopic $row) => $order[$row->locale] ?? PHP_INT_MAX)
            ->groupBy('topic')
            ->map(
                fn($rows) => $rows->first(fn(HelpTopic $row) => $this->isVisibleFor($row, $user))
            )
            ->filter()
            ->values();
    }

    public function isVisibleFor(HelpTopic $topic, ?User $user): bool {
        // Modul-Gating (Feature 039, MVP-753): Front-Matter `modules` —
        // leere Liste = modulunabhängig, sonst reicht EIN freigeschaltetes
        // Modul (analog audience). Greift für Drawer UND Hilfecenter.
        if (! $this->modulesEnabled($topic)) {
            return false;
        }

        $audience = $topic->audience;
        if (! is_array($audience) || $audience === []) {
            return true;
        }
        if (in_array('*', $audience, true)) {
            return true;
        }
        if ($user === null) {
            return false;
        }

        foreach ($audience as $code) {
            if ($code === '') {
                continue;
            }
            if ($user->hasRole($code)) {
                return true;
            }
        }

        return false;
    }

    private function modulesEnabled(HelpTopic $topic): bool {
        $modules = $topic->modules;
        if (! is_array($modules) || $modules === []) {
            return true;
        }

        foreach ($modules as $code) {
            if ($code !== '' && $this->features->isEnabled($code)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function localeFallbackChain(?string $preferredLocale): array {
        $primary = $preferredLocale ?? App::getLocale();
        $chain = [$primary];
        if ($primary !== 'de') {
            $chain[] = 'de';
        }
        if (! in_array('en', $chain, true)) {
            $chain[] = 'en';
        }

        return $chain;
    }
}
