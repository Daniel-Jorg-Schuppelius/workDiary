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

use App\Models\{HelpTopic, User};
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
        $locales = $this->localeFallbackChain($preferredLocale);

        $candidates = HelpTopic::query()
            ->where('topic', $topic)
            ->whereIn('locale', $locales)
            ->get()
            ->keyBy('locale');

        foreach ($locales as $locale) {
            /** @var HelpTopic|null $row */
            $row = $candidates->get($locale);
            if ($row !== null && $this->isVisibleFor($row, $user)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Volltextsuche auf body_md + title, eingeschränkt auf zugelassene Audiences.
     *
     * @return \Illuminate\Support\Collection<int, HelpTopic>
     */
    public function search(string $query, ?User $user = null, ?string $preferredLocale = null, int $limit = 20): \Illuminate\Support\Collection {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $locale = $this->localeFallbackChain($preferredLocale)[0];

        $rows = HelpTopic::query()
            ->where('locale', $locale)
            ->where(function ($q) use ($query): void {
                $q->whereLikeEscaped('title', $query)
                    ->orWhereLikeEscaped('body_md', $query)
                    ->orWhereLikeEscaped('topic', $query);
            })
            ->limit($limit * 3)
            ->get();

        return $rows
            ->filter(fn(HelpTopic $row) => $this->isVisibleFor($row, $user))
            ->values()
            ->take($limit);
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
     * Volltextsuche für die Hilfecenter-Vollseite: Kandidaten OHNE
     * longText-Spalten hydrieren (Hydration-Kosten), Sichtbarkeit in PHP
     * filtern (JSON-Spalten), dann paginieren. Trefferzahl zählt nur
     * sichtbare Topics — kein Berechtigungs-Orakel über total().
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, HelpTopic>
     */
    public function searchPaginated(string $query, ?User $user = null, ?string $preferredLocale = null, int $perPage = 20, int $page = 1): \Illuminate\Pagination\LengthAwarePaginator {
        $query = trim($query);
        $locale = $this->localeFallbackChain($preferredLocale)[0];

        $visible = collect();
        if ($query !== '') {
            $visible = HelpTopic::query()
                ->where('locale', $locale)
                ->where(function ($q) use ($query): void {
                    $q->whereLikeEscaped('title', $query)
                        ->orWhereLikeEscaped('body_md', $query)
                        ->orWhereLikeEscaped('topic', $query);
                })
                ->orderBy('title')
                ->get(['id', 'topic', 'locale', 'title', 'audience', 'modules', 'version'])
                ->filter(fn(HelpTopic $row) => $this->isVisibleFor($row, $user))
                ->values();
        }

        // Snippets nur für die aktuelle Seite: body_md gezielt nachladen
        // (longText bleibt aus der Kandidaten-Hydration draußen, MVP-753).
        $pageItems = $visible->forPage($page, $perPage)->values();
        if ($query !== '' && $pageItems->isNotEmpty()) {
            $bodies = HelpTopic::query()
                ->whereIn('id', $pageItems->pluck('id'))
                ->pluck('body_md', 'id');
            foreach ($pageItems as $row) {
                $row->setAttribute('search_snippet', $this->snippetFor((string) $bodies->get($row->id, ''), $query));
            }
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItems,
            $visible->count(),
            $perPage,
            $page,
        );
    }

    /**
     * Treffer-Ausschnitt als [vor, Treffer, nach] — ROHE Segmente, die View
     * escaped jedes einzeln und hebt nur den Treffer mit <mark> hervor
     * (kein HTML aus body_md in der Seite). Ohne Body-Treffer (Titel-/
     * Topic-Treffer) beginnt der Ausschnitt am Textanfang.
     *
     * @return array{0:string, 1:string, 2:string}
     */
    private function snippetFor(string $bodyMd, string $query): array {
        $text = trim((string) preg_replace('/\s+/u', ' ', $bodyMd));

        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            $lead = mb_substr($text, 0, 160);

            return ['', '', $lead . (mb_strlen($text) > 160 ? '…' : '')];
        }

        $start = max(0, $pos - 60);
        $pre = ($start > 0 ? '…' : '') . mb_substr($text, $start, $pos - $start);
        $hit = mb_substr($text, $pos, mb_strlen($query));
        $rest = mb_substr($text, $pos + mb_strlen($query), 120);
        $post = $rest . (mb_strlen($text) > $pos + mb_strlen($query) + 120 ? '…' : '');

        return [$pre, $hit, $post];
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
