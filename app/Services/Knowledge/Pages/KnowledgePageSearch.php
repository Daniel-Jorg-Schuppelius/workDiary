<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageSearch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Models\Knowledge\{KnowledgeGlossaryTerm, KnowledgePage, KnowledgeSearchGap};
use App\Models\Platform\User;
use App\Services\Search\SearchTextNormalizer;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Suche in den Wissensseiten (Feature 191, MVP-1667): Titel und Text, mit
 * Glossar — ein Kürzel oder Synonym findet auch den Begriff und umgekehrt
 * („USt-VA“ findet „Umsatzsteuer-Voranmeldung“). Wer nicht bearbeiten darf,
 * durchsucht die freigegebene Fassung, nicht den Entwurf.
 *
 * Suchlücken zählen Begriffe ohne Treffer — ohne Person, ohne Zeitpunkt je
 * Suche, nur Anzahl, erste und letzte Suche.
 */
final class KnowledgePageSearch {
    public const int MIN_GAP_LENGTH = 3;

    private const int MAX_FORMS = 12;

    public function __construct(
        private readonly KnowledgePageAccess $access,
        private readonly SearchTextNormalizer $normalizer,
    ) {}

    /**
     * Suchformen: der Begriff selbst, dazu alle Formen der Glossarbegriffe,
     * deren Begriff, Kürzel oder Synonym dem Ganzen oder einem Wort entspricht.
     *
     * @return list<string>
     */
    public function expand(int $organizationId, string $term): array {
        $term = StringHelper::normalizeWhitespace($term);
        if ($term === '') {
            return [];
        }
        $wanted = [$this->normalizer->key($term) => true];
        foreach (StringHelper::words($term) as $word) {
            $wanted[$this->normalizer->key($word)] = true;
        }
        unset($wanted['']);

        $forms = [StringHelper::toLower($term) => $term];
        foreach (KnowledgeGlossaryTerm::query()->where('organization_id', $organizationId)->get(['term', 'synonyms']) as $entry) {
            $entryForms = $entry->forms();
            foreach ($entryForms as $form) {
                if (isset($wanted[$this->normalizer->key($form)])) {
                    foreach ($entryForms as $other) {
                        $forms[StringHelper::toLower($other)] ??= $other;
                    }

                    break;
                }
            }
        }

        return array_slice(array_values($forms), 0, self::MAX_FORMS);
    }

    /**
     * Treffer-Bedingung auf eine Seitenabfrage legen.
     *
     * @param  Builder<KnowledgePage>  $query
     * @param  list<string>  $forms
     */
    public function apply(Builder $query, User $user, array $forms): void {
        if ($forms === []) {
            return;
        }
        if ($this->access->canEdit($user)) {
            $query->where(static function (Builder $match) use ($forms): void {
                foreach ($forms as $form) {
                    $match->orWhere(static fn (Builder $q) => $q->whereLikeEscaped('knowledge_pages.title', $form)->orWhereLikeEscaped('knowledge_pages.body', $form));
                }
            });

            return;
        }
        // Freigegebene Fassung durchsuchen: Entwurfstext verrät sonst, was noch nicht gilt.
        $query->whereExists(static function (QueryBuilder $version) use ($forms): void {
            $version->selectRaw('1')
                ->from('knowledge_page_versions')
                ->whereColumn('knowledge_page_versions.knowledge_page_id', 'knowledge_pages.id')
                ->whereColumn('knowledge_page_versions.version', 'knowledge_pages.approved_version')
                ->where(static function (QueryBuilder $match) use ($forms): void {
                    foreach ($forms as $form) {
                        $match->orWhere(static fn (QueryBuilder $q) => $q->whereLikeEscaped('knowledge_page_versions.title', $form)->orWhereLikeEscaped('knowledge_page_versions.body', $form));
                    }
                });
        });
    }

    /** Suchbegriff ohne Treffer zählen — ohne Personenbezug. */
    public function recordGap(int $organizationId, string $term): void {
        $key = StringHelper::truncate(StringHelper::toLower(StringHelper::normalizeWhitespace($term)), 120, '');
        if (mb_strlen($key) < self::MIN_GAP_LENGTH) {
            return;
        }
        $now = CarbonImmutable::now();
        DB::transaction(static function () use ($organizationId, $key, $now): void {
            $gap = KnowledgeSearchGap::query()->where('organization_id', $organizationId)->where('term', $key)->lockForUpdate()->first();
            if ($gap === null) {
                KnowledgeSearchGap::query()->create([
                    'organization_id' => $organizationId,
                    'term' => $key,
                    'searches' => 1,
                    'first_searched_at' => $now,
                    'last_searched_at' => $now,
                ]);

                return;
            }
            // Wer wieder ins Leere sucht, öffnet eine erledigte Lücke erneut.
            $gap->forceFill(['searches' => $gap->searches + 1, 'last_searched_at' => $now, 'dismissed_at' => null])->save();
        });
    }

    /**
     * Textauszug um die erste Fundstelle, ohne Markdown-Zeichen.
     *
     * @param  list<string>  $forms
     */
    public function snippet(?string $body, array $forms, int $length = 160): string {
        $plain = StringHelper::normalizeWhitespace((string) preg_replace('/[#>*_`~\[\]()!|:-]+/u', ' ', (string) $body));
        if ($plain === '') {
            return '';
        }
        $position = null;
        foreach ($forms as $form) {
            $found = mb_stripos($plain, $form);
            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }
        $start = max(0, ($position ?? 0) - 40);

        return ($start > 0 ? '…' : '') . StringHelper::truncate(mb_substr($plain, $start), $length, '…');
    }
}
