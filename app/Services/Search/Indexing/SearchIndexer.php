<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchIndexer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing;

use App\Enums\Search\SearchSourceType;
use App\Models\Search\SearchDocument;
use App\Services\Search\Indexing\Sources\SearchSource;
use App\Services\Search\{SearchTextNormalizer, SearchVocabulary};
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Einzige Schreibstelle von `search_documents` (Feature 153, MVP-770).
 *
 * Observer rufen {@see schedule()}: indiziert wird nach dem Commit — eine
 * zurückgerollte Transaktion hinterlässt nichts, und mehrere Änderungen am
 * selben Datensatz innerhalb einer Transaktion indizieren nur einmal. Ein
 * Indexfehler bricht nie die fachliche Speicherung; `search:reconcile` heilt.
 */
final class SearchIndexer {
    /** Spalten, über die Kontextänderungen (Umbenennen/Umhängen) nachgezogen werden. */
    public const CONTEXT_COLUMNS = ['project_id', 'foreign_customer_id', 'customer_id'];

    /** @var array<string, int> */
    private array $versions = [];

    public function __construct(
        private readonly SearchSourceRegistry $sources,
        private readonly SearchContext $context,
        private readonly SearchTextNormalizer $normalizer,
        private readonly SearchVocabulary $vocabulary,
    ) {}

    public function schedule(Model $model): void {
        if (! config('search.indexing', true)) {
            return;
        }

        $target = $this->sources->target($model);
        if ($target === null) {
            return;
        }

        [$type, $id] = $target;
        $key = $type->value . ':' . $id;
        $version = $this->versions[$key] = ($this->versions[$key] ?? 0) + 1;

        DB::afterCommit(function () use ($type, $id, $key, $version): void {
            if (($this->versions[$key] ?? 0) !== $version) {
                return; // Eine spätere Änderung derselben Transaktion indiziert.
            }
            unset($this->versions[$key]);

            try {
                $this->index($type, $id);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function index(SearchSourceType $type, int $id): void {
        $this->indexMany($type, [$id]);
    }

    /**
     * Dokumente zu Quell-IDs neu schreiben; nicht (mehr) indizierbare entfallen.
     *
     * @param  list<int>  $ids
     */
    public function indexMany(SearchSourceType $type, array $ids): int {
        if ($ids === []) {
            return 0;
        }

        $source = $this->sources->get($type);
        $models = $source->query(null)->whereKey($ids)->get();
        $written = $this->writeModels($source, $models);

        $missing = array_diff($ids, $models->modelKeys());
        if ($missing !== []) {
            $this->removeMany($type, array_values($missing));
        }

        return $written;
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function writeModels(SearchSource $source, Collection $models): int {
        $rows = [];
        $obsolete = [];

        foreach ($models as $model) {
            $data = $source->build($model, $this->context);
            if ($data === null) {
                $obsolete[] = (int) $model->getKey();

                continue;
            }
            $rows[] = $this->row($data);
        }

        if ($obsolete !== []) {
            $this->removeMany($source->type(), $obsolete);
        }
        if ($rows === []) {
            return 0;
        }

        $documents = array_map(static fn(array $r): array => $r['row'], $rows);
        SearchDocument::query()->withoutGlobalScopes()->upsert(
            $documents,
            ['source_type', 'source_id'],
            array_values(array_diff(array_keys($documents[0]), ['source_type', 'source_id'])),
        );

        foreach ($rows as $row) {
            $this->vocabulary->record($row['row']['organization_id'], $row['tokens']);
        }

        return count($rows);
    }

    /** @param  list<int>  $ids */
    public function removeMany(SearchSourceType $type, array $ids): void {
        SearchDocument::query()
            ->withoutGlobalScopes()
            ->where('source_type', $type->value)
            ->whereIn('source_id', $ids)
            ->delete();
    }

    /** Dokumente entfernen, deren Quelle fehlt oder nicht mehr indizierbar ist. */
    public function removeOrphans(SearchSource $source, ?int $organizationId): int {
        $model = $source->query(null)->getModel();
        $indexable = $source->query($organizationId)->select($model->qualifyColumn($model->getKeyName()))->toBase();

        $documents = SearchDocument::query()->withoutGlobalScopes()->where('source_type', $source->type()->value);
        if ($organizationId !== null) {
            $documents->where('organization_id', $organizationId);
        }

        return $documents->whereNotIn('source_id', $indexable)->delete();
    }

    /**
     * Alle Dokumente mit diesem Kontextbezug neu schreiben (Projekt, Endkunde
     * oder Kunde umbenannt/umgehängt).
     */
    public function reindexContext(string $column, int $id): int {
        if (! in_array($column, self::CONTEXT_COLUMNS, true)) {
            throw new \InvalidArgumentException('Unbekannte Kontextspalte: ' . $column);
        }

        // Der Kontext-Memo kennt noch den alten Namen/Bezug — vorher leeren.
        $this->context->flush();

        $total = 0;
        SearchDocument::query()
            ->withoutGlobalScopes()
            ->where($column, $id)
            ->select(['id', 'source_type', 'source_id'])
            ->chunkById(500, function (Collection $documents) use (&$total): void {
                foreach ($documents->groupBy(static fn(SearchDocument $d): string => $d->source_type->value) as $type => $group) {
                    $total += $this->indexMany(SearchSourceType::from((string) $type), array_values($group->pluck('source_id')->map(static fn($v): int => (int) $v)->all()));
                }
            });
        $this->context->flush();

        return $total;
    }

    /**
     * @return array{row: array<string, mixed>, tokens: list<string>}
     */
    private function row(SearchDocumentData $data): array {
        $tokens = [];
        foreach ([...$data->primaryTexts, ...$data->texts, ...$data->primaryTexts] as $text) {
            if (is_string($text) && $text !== '') {
                foreach ($this->normalizer->tokens($text) as $token) {
                    $tokens[] = $token;
                }
            }
        }

        $excerpt = $data->excerpt !== null && trim($data->excerpt) !== ''
            ? StringHelper::truncate(trim($data->excerpt), (int) config('search.excerpt_length', 2000), '…')
            : null;

        return [
            'row' => [
                'organization_id' => $data->organizationId,
                'source_type' => $data->type->value,
                'source_id' => $data->sourceId,
                'occurred_at' => self::utc($data->occurredAt),
                'date_only' => $data->dateOnly,
                'user_id' => $data->userId,
                'assigned_user_id' => $data->assignedUserId,
                'customer_id' => $data->customerId,
                'foreign_customer_id' => $data->foreignCustomerId,
                'project_id' => $data->projectId,
                'minutes' => $data->minutes,
                'restricted' => $data->restricted,
                'title' => StringHelper::truncate($data->title, 255, '…'),
                'excerpt' => $excerpt,
                'search_text' => $this->normalizer->encode($tokens, (int) config('search.text_max_length', 60000)),
                'source_updated_at' => self::utc($data->sourceUpdatedAt),
            ],
            'tokens' => array_values(array_unique($tokens)),
        ];
    }

    // TIMESTAMP-Spalten fassen nur 1970–2038: ein vertipptes Jahr in der Quelle
    // (DATETIME) brach sonst die Indizierung ab — und damit deren Speichern.
    private static function utc(?CarbonInterface $value): ?string {
        if ($value === null) {
            return null;
        }
        $utc = CarbonImmutable::instance($value)->utc();

        return $utc->timestamp < 1 || $utc->timestamp > 2147483647 ? null : $utc->format('Y-m-d H:i:s');
    }
}
