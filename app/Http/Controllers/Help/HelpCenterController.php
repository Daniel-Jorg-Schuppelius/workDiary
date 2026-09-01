<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpCenterController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Models\{HelpView, User};
use App\Services\Help\{HelpCenterCatalog, HelpTopicResolver};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Hilfecenter-Vollseite (Feature 039, MVP-752): durchsuchbare Übersicht mit
 * Themenbereichen plus ausführliche Artikelseiten — aus DENSELBEN
 * help_topics-Zeilen wie der Drawer (ein Inhaltsbestand, eine Sichtbarkeit).
 * Der Drawer-JSON-Endpunkt (HelpController) bleibt unverändert daneben.
 */
class HelpCenterController extends Controller {
    private const PER_PAGE = 20;

    public function index(Request $request, HelpTopicResolver $resolver, HelpCenterCatalog $catalog): View {
        /** @var User|null $user */
        $user = $request->user();

        $query = trim((string) $request->query('q', ''));
        $sectionKey = trim((string) $request->query('bereich', ''));
        $page = max(1, (int) $request->query('page', 1));

        // Suchmodus: paginierte Treffer mit Bereichslabel je Zeile.
        if ($query !== '') {
            $results = $resolver->searchPaginated($query, $user, null, self::PER_PAGE, $page);
            $results->withPath($request->url())->appends($request->except('page'));

            return view('help.center.index', [
                'mode' => 'search',
                'query' => $query,
                'results' => $results,
                'sectionTitles' => $this->sectionTitles($catalog),
                'catalog' => $catalog,
            ]);
        }

        $topics = $resolver->visibleForLocale($user);
        $grouped = $catalog->grouped($topics);

        // Bereichsansicht: sichtbare Artikel EINES Bereichs, paginiert.
        if ($sectionKey !== '') {
            $section = collect($catalog->sections())->firstWhere('key', $sectionKey);
            $rows = $grouped[$sectionKey] ?? collect();
            abort_if($section === null || $rows->isEmpty(), 404);

            $results = new LengthAwarePaginator(
                $rows->forPage($page, self::PER_PAGE)->values(),
                $rows->count(),
                self::PER_PAGE,
                $page,
            );
            $results->withPath($request->url())->appends($request->except('page'));

            return view('help.center.index', [
                'mode' => 'section',
                'section' => $section,
                'results' => $results,
                'sectionTitles' => $this->sectionTitles($catalog),
                'catalog' => $catalog,
            ]);
        }

        // Kachelübersicht: Bereiche mit Zahl der sichtbaren Artikel.
        $sections = collect($catalog->sections())
            ->map(fn(array $section): array => $section + ['count' => ($grouped[$section['key']] ?? collect())->count()])
            ->filter(fn(array $section): bool => $section['count'] > 0)
            ->values()
            ->all();

        return view('help.center.index', [
            'mode' => 'overview',
            'sections' => $sections,
            'totalCount' => $topics->count(),
            'popular' => $this->popularTopics($user, $topics, $catalog),
        ]);
    }

    /**
     * „Beliebte Themen" (MVP-755): meistgelesene Topics der eigenen
     * Organisation (HelpView, 90 Tage) — gecacht wird nur die anonyme
     * Roh-Zählung je Org; der Sichtbarkeitsfilter läuft pro Nutzer über die
     * bereits geladenen sichtbaren Topics (kein Berechtigungs-Orakel).
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\HelpTopic> $visibleTopics
     * @return list<array{topic:string, title:string, section:string}>
     */
    private function popularTopics(?User $user, \Illuminate\Support\Collection $visibleTopics, HelpCenterCatalog $catalog): array {
        $orgId = $user?->organization_id;
        if ($orgId === null) {
            return [];
        }

        /** @var array<string, int> $counts */
        $counts = Cache::remember(
            'help-center:popular:' . $orgId,
            60,
            static fn(): array => HelpView::query()
                ->where('organization_id', $orgId)
                ->where('created_at', '>=', CarbonImmutable::now()->subDays(90))
                ->groupBy('topic')
                ->selectRaw('topic, COUNT(*) AS views')
                ->orderByDesc('views')
                ->limit(24)
                ->pluck('views', 'topic')
                ->all(),
        );

        $byTopic = $visibleTopics->keyBy('topic');
        $popular = [];
        foreach (array_keys($counts) as $topic) {
            $row = $byTopic->get($topic);
            if ($row === null) {
                continue;
            }
            $popular[] = [
                'topic' => $row->topic,
                'title' => $row->title,
                'section' => (string) __('help.sections.' . $catalog->sectionKeyFor($row->topic) . '.title'),
            ];
            if (count($popular) === 6) {
                break;
            }
        }

        return $popular;
    }

    public function show(Request $request, HelpTopicResolver $resolver, HelpCenterCatalog $catalog, string $topic): View {
        /** @var User|null $user */
        $user = $request->user();

        // Unbekannt, unsichtbar oder ohne Übersetzung in der Fallback-Kette:
        // einheitliches 404, keine Inhaltsmetadaten, kein Berechtigungs-Orakel.
        $row = $resolver->find($topic, $user);
        abort_if($row === null, 404);

        HelpView::query()->create([
            'organization_id' => $user?->organization_id,
            'topic' => $row->topic,
            'locale' => $row->locale,
            'was_helpful' => null,
            'created_at' => CarbonImmutable::now(),
        ]);

        $sectionKey = $catalog->sectionKeyFor($row->topic);

        return view('help.center.show', [
            'row' => $row,
            'related' => $resolver->relatedFor($row, $user),
            'headings' => array_values(array_filter(
                $row->headings ?? [],
                static fn(array $heading): bool => $heading['level'] === 2,
            )),
            'sectionKey' => $sectionKey,
            'sectionTitle' => __('help.sections.' . $sectionKey . '.title'),
        ]);
    }

    /** @return array<string, string> Bereichs-Key → übersetzter Titel. */
    private function sectionTitles(HelpCenterCatalog $catalog): array {
        $titles = [];
        foreach ($catalog->sections() as $section) {
            $titles[$section['key']] = $section['title'];
        }

        return $titles;
    }
}
