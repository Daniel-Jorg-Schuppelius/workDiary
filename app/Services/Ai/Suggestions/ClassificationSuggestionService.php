<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Enums\Classification\ClassificationDomain;
use App\Models\{Classification, Customer, DiaryEntry, Organization, Tag};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiClassificationResult, ClassifyRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Classification\ClassificationResolver;
use Illuminate\Support\Collection;

/**
 * KI-Welle 1 für Klassifikationen (Feature 143, MVP-711): schlägt Tags und
 * Katalogwerte aus Freitext vor. Katalog = die BESTEHENDEN Tags/Katalogwerte
 * der Organisation; alles, was der Provider darüber hinaus liefert, wird
 * verworfen — die KI legt nie Tags an, die Übernahme ist immer ein Klick
 * im regulären Tag-Input.
 */
class ClassificationSuggestionService {
    public const CAPABILITY = 'classification.tag_suggest';

    /** Obergrenze Katalogwerte je Aufruf (Prompt-Größe). */
    private const CATALOG_LIMIT = 500;

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly CustomerNameMasker $masker,
        private readonly ClassificationResolver $classifications,
    ) {}

    /**
     * Passende bestehende Tags in Vorschlagsreihenfolge. Mit Kunde stehen
     * dessen zuletzt genutzte Tags vorn im Katalog (schwacher Prior, keine
     * Kundendaten im Prompt).
     *
     * @return Collection<int, Tag>
     */
    public function suggestTags(Organization $organization, string $text, ?Customer $customer = null, ?int $connectionId = null): Collection {
        $text = trim($text);
        if ($text === '') {
            return new Collection;
        }

        /** @var Collection<int, Tag> $tags */
        $tags = Tag::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->limit(self::CATALOG_LIMIT)
            ->get();
        if ($tags->isEmpty()) {
            return new Collection;
        }

        $preferred = $customer !== null ? $this->recentTagIdsFor($organization, $customer) : [];
        $tags = $tags->sortBy(static fn (Tag $t): int => ($pos = array_search((int) $t->id, $preferred, true)) === false ? PHP_INT_MAX : $pos)->values();

        $byName = [];
        foreach ($tags as $tag) {
            $byName[mb_strtolower(trim((string) $tag->name))] ??= $tag;
        }
        $catalog = array_map(static fn (Tag $t): string => (string) $t->name, array_values($byName));

        $values = $this->classify($organization, $text, $catalog, $connectionId);

        $matched = new Collection;
        foreach ($values as $value) {
            $tag = $byName[mb_strtolower(trim($value))] ?? null;
            if ($tag !== null && ! $matched->contains('id', $tag->id)) {
                $matched->push($tag);
            }
        }

        return $matched;
    }

    /**
     * Passende Katalogwerte einer Domäne (Org-Werte + Plattform-Defaults).
     *
     * @return Collection<int, Classification>
     */
    public function suggestCatalogValues(Organization $organization, ClassificationDomain $domain, string $text, ?int $connectionId = null): Collection {
        $text = trim($text);
        if ($text === '') {
            return new Collection;
        }

        $byLabel = [];
        foreach ($this->classifications->list((int) $organization->id, $domain)->take(self::CATALOG_LIMIT) as $classification) {
            $byLabel[mb_strtolower(trim((string) $classification->label))] ??= $classification;
        }
        if ($byLabel === []) {
            return new Collection;
        }
        $catalog = array_map(static fn (Classification $c): string => (string) $c->label, array_values($byLabel));

        $values = $this->classify($organization, $text, $catalog, $connectionId);

        $matched = new Collection;
        foreach ($values as $value) {
            $classification = $byLabel[mb_strtolower(trim($value))] ?? null;
            if ($classification !== null && ! $matched->contains('id', $classification->id)) {
                $matched->push($classification);
            }
        }

        return $matched;
    }

    /**
     * Werte sind bereits durch den AiInvocationService auf den Katalog
     * gefiltert; das Rückmapping der Aufrufer ist zusätzlich case-insensitiv
     * und verwirft alles, was keinem Modell entspricht.
     *
     * @param list<string> $catalog
     * @return list<string>
     */
    private function classify(Organization $organization, string $text, array $catalog, ?int $connectionId): array {
        $request = new ClassifyRequest(
            text: $this->masker->mask($organization, $text),
            catalog: $catalog,
            multiple: true,
            language: app()->getLocale(),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiClassificationResult) {
            throw new AiException((string) __('ai.error.unexpected_classification_type'));
        }

        return $payload->values;
    }

    /** @return list<int> */
    private function recentTagIdsFor(Organization $organization, Customer $customer): array {
        /** @var list<int> $ids */
        $ids = DiaryEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(50)
            ->with('tags:id')
            ->get(['id'])
            ->flatMap(static fn (DiaryEntry $e): array => $e->tags->pluck('id')->map(static fn ($id): int => (int) $id)->all())
            ->unique()
            ->values()
            ->all();

        return $ids;
    }
}
