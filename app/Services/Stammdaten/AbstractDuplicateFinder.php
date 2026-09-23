<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Services\Integration\Match\{EntityMatcher, MatchProfile, MatchStrategy};
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};
use Illuminate\Support\Collection;

/**
 * Gemeinsames Gerüst der Dubletten-Finder (Kunde/Projekt): lädt die Kandidaten
 * des {@see MatchProfile}, vergleicht paarweise via {@see EntityMatcher}
 * (Wertesätze einmal vorab extrahiert statt O(n²)-mal), filtert dismisste
 * Paare und ordnet Ziel/Quelle über die Score-Heuristik der Subklasse.
 *
 * @template TModel of Model
 */
abstract class AbstractDuplicateFinder {
    /** Stufen entsprechen {@see MatchStrategy}; Konstanten für UI/Tests. */
    public const CONF_EXACT = MatchStrategy::EXACT;
    public const CONF_LIKELY = MatchStrategy::LIKELY;
    public const CONF_FUZZY = MatchStrategy::FUZZY;

    /** @var array<string, int> */
    protected const RANK = [
        MatchStrategy::FUZZY => 1,
        MatchStrategy::LIKELY => 2,
        MatchStrategy::EXACT => 3,
    ];

    public function __construct(protected readonly EntityMatcher $matcher) {}

    /** Match-Profil für Vergleich und Feld-Extraktion. */
    abstract protected function profile(): MatchProfile;

    /**
     * Lädt die Vergleichskandidaten (Profil-Query + benötigte withCount()).
     *
     * @return EloquentCollection<int, TModel>
     */
    abstract protected function fetchCandidates(Organization $organization): EloquentCollection;

    /**
     * Ziel-Heuristik: höheres Score-Tupel bleibt bestehen (Ziel).
     *
     * @param  TModel  $model
     * @return list<int>
     */
    abstract protected function score(Model $model): array;

    /** @return class-string<Model> Dismissal-Modell (organization_id + low/high-Spalten). */
    abstract protected function dismissalModel(): string;

    /** @return array{0: string, 1: string} [low-Spalte, high-Spalte] des Dismissal-Modells. */
    abstract protected function dismissalKeyColumns(): array;

    /**
     * Vergleichsgruppe: Paare werden nur innerhalb derselben Gruppe gebildet
     * (Standard: eine Gruppe für alle Kandidaten).
     *
     * @param  TModel  $model
     */
    protected function groupKey(Model $model): int|string {
        return 0;
    }

    /**
     * Paar fachlich ausschließen (z. B. Eltern/Kind), bevor verglichen wird.
     *
     * @param  TModel  $a
     * @param  TModel  $b
     */
    protected function skipPair(Model $a, Model $b): bool {
        return false;
    }

    /**
     * @param  string|null  $onlyConfidence  Auf eine Stufe einschränken (z. B. nur 'exact').
     * @return Collection<int, array{source: TModel, target: TModel, confidence: string, reasons: list<string>}>
     */
    public function candidates(Organization $organization, ?string $onlyConfidence = null): Collection {
        $profile = $this->profile();
        $models = $this->fetchCandidates($organization);
        $dismissed = $this->dismissedKeys($organization);

        /** @var array<string, array{source: TModel, target: TModel, confidence: string, reasons: list<string>}> $pairs */
        $pairs = [];

        foreach ($models->groupBy(fn(Model $m): int|string => $this->groupKey($m)) as $group) {
            $list = $group->values()->all();
            $count = count($list);

            // Wertesätze einmal vorab extrahieren (statt O(n²)-mal).
            $fields = [];
            foreach ($list as $i => $model) {
                $fields[$i] = $profile->extract($model);
            }

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $list[$i];
                    $b = $list[$j];

                    if ($this->skipPair($a, $b)) {
                        continue;
                    }

                    $cmp = $this->matcher->compare($profile, $fields[$i], $fields[$j]);
                    if ($cmp['confidence'] === null) {
                        continue;
                    }

                    $key = min((int) $a->getKey(), (int) $b->getKey()) . '-' . max((int) $a->getKey(), (int) $b->getKey());
                    if (isset($dismissed[$key])) {
                        continue;
                    }
                    if ($onlyConfidence !== null && $cmp['confidence'] !== $onlyConfidence) {
                        continue;
                    }

                    [$target, $source] = $this->orderTargetSource($a, $b);
                    $pairs[$key] = [
                        'source' => $source,
                        'target' => $target,
                        'confidence' => $cmp['confidence'],
                        'reasons' => $cmp['reasons'],
                    ];
                }
            }
        }

        return Collection::make($pairs)
            ->sortByDesc(fn(array $p): int => self::RANK[$p['confidence']])
            ->values();
    }

    /**
     * Bestimmt Ziel (bleibt) und Quelle (geht auf) über {@see score()}.
     *
     * @param  TModel  $a
     * @param  TModel  $b
     * @return array{0: TModel, 1: TModel}  [Ziel, Quelle]
     */
    protected function orderTargetSource(Model $a, Model $b): array {
        return $this->score($a) >= $this->score($b) ? [$a, $b] : [$b, $a];
    }

    /**
     * @return array<string, true>  Schlüssel "lowId-highId" der dismissten Paare.
     */
    protected function dismissedKeys(Organization $organization): array {
        [$lowCol, $highCol] = $this->dismissalKeyColumns();

        return $this->dismissalModel()::query()
            ->where('organization_id', $organization->id)
            ->get([$lowCol, $highCol])
            ->mapWithKeys(fn(Model $d): array => [
                $d->getAttribute($lowCol) . '-' . $d->getAttribute($highCol) => true,
            ])
            ->all();
    }
}
