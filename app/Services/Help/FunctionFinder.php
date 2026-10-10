<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FunctionFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

use App\Models\Platform\HelpTopic;
use App\Services\Navigation\NavigationPages;

/**
 * Wegweiser von einer Suchanfrage zur Funktion (MVP-1082): Seiten der
 * Navigation und Bedienaktionen, bewertet wie Hilfethemen. Eine Seite trägt
 * Titel und Suchbegriffe ihres Hilfethemas (Route → Thema laut
 * config/help-topics.php) — „Urlaub" findet so „Abwesenheiten".
 */
final class FunctionFinder {
    /** Bedienaktionen der Befehlspalette (Schlüssel → Symbol); ausgeführt in global-search.js. */
    public const ACTIONS = [
        'theme' => 'dark_mode',
        'shortcuts' => 'keyboard',
        'help' => 'help',
    ];

    /** @var list<array{route: string, label: string, icon: string, area: string, url: string, modal: bool, topic: string|null}>|null */
    private ?array $pages = null;

    public function __construct(
        private readonly HelpSearch $search,
        private readonly NavigationPages $navigation,
        private readonly HelpContextResolver $context,
    ) {}

    /**
     * Seiten, die alle Pflichtwörter tragen — beste zuerst. Teiltreffer
     * bleiben draußen: ein Sprungziel soll passen.
     *
     * @return list<array{route: string, label: string, icon: string, area: string, url: string, modal: bool, topic: string|null, keyword: string|null}>
     */
    public function pages(HelpSearchQuery $query, int $limit): array {
        $pages = $this->pagesWithTopics();
        $topics = HelpTopic::query()
            ->where('locale', $query->locale)
            ->whereIn('topic', array_values(array_unique(array_filter(array_column($pages, 'topic')))))
            ->get(['topic', 'title', 'keywords'])
            ->keyBy('topic');

        $ranked = $this->search->rank($query, $pages, static function (array $page) use ($topics): array {
            $topic = $page['topic'] !== null ? $topics->get($page['topic']) : null;

            return [
                'title' => ['weight' => HelpSearch::WEIGHT_TITLE, 'texts' => [$page['label']]],
                'keywords' => ['weight' => HelpSearch::WEIGHT_KEYWORDS, 'texts' => array_map('strval', $topic->keywords ?? [])],
                'headings' => ['weight' => HelpSearch::WEIGHT_HEADINGS, 'texts' => array_values(array_filter([$page['area'], (string) $topic?->title]))],
            ];
        });

        return array_slice(array_map(
            static fn(array $hit): array => $hit['entry'] + ['keyword' => $hit['keyword']],
            array_values(array_filter($ranked['hits'], static fn(array $hit): bool => $hit['complete'])),
        ), 0, $limit);
    }

    /**
     * Erste erreichbare Seite je Hilfethema — Sprungziel neben einem
     * Hilfetreffer.
     *
     * @return array<string, array{label: string, url: string, modal: bool}>
     */
    public function pagesByTopic(): array {
        $byTopic = [];
        foreach ($this->pagesWithTopics() as $page) {
            if ($page['topic'] !== null && ! isset($byTopic[$page['topic']])) {
                $byTopic[$page['topic']] = ['label' => $page['label'], 'url' => $page['url'], 'modal' => $page['modal']];
            }
        }

        return $byTopic;
    }

    /** @return list<array{key: string, icon: string, label: string, hint: string, keyword: string|null}> */
    public function actions(HelpSearchQuery $query): array {
        $actions = [];
        foreach (self::ACTIONS as $key => $icon) {
            $actions[] = [
                'key' => $key,
                'icon' => $icon,
                'label' => (string) __('search.palette.actions.' . $key . '.label'),
                'hint' => (string) __('search.palette.actions.' . $key . '.hint'),
                'keywords' => array_values(array_filter(array_map('trim', explode(',', (string) __('search.palette.actions.' . $key . '.keywords'))))),
            ];
        }

        $ranked = $this->search->rank($query, $actions, static fn(array $action): array => [
            'title' => ['weight' => HelpSearch::WEIGHT_TITLE, 'texts' => [$action['label']]],
            'keywords' => ['weight' => HelpSearch::WEIGHT_KEYWORDS, 'texts' => $action['keywords']],
        ]);

        return array_map(static fn(array $hit): array => [
            'key' => $hit['entry']['key'],
            'icon' => $hit['entry']['icon'],
            'label' => $hit['entry']['label'],
            'hint' => $hit['entry']['hint'],
            'keyword' => $hit['keyword'],
        ], array_values(array_filter($ranked['hits'], static fn(array $hit): bool => $hit['complete'])));
    }

    /** @return list<array{route: string, label: string, icon: string, area: string, url: string, modal: bool, topic: string|null}> */
    private function pagesWithTopics(): array {
        return $this->pages ??= array_map(
            fn(array $page): array => $page + ['topic' => $this->context->topicForRouteName($page['route'])],
            $this->navigation->forCurrentUser(),
        );
    }
}
