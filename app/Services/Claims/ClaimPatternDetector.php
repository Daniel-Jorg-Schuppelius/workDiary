<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimPatternDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Claims;

use App\Models\Claims\ClaimCase;
use App\Models\Platform\Organization;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Serienfehler und Chargenprobleme (Feature 072, MVP-886): feste Regeln über
 * die Reklamationen eines Zeitraums. Ein Muster ist eine Gruppe mit
 * mindestens `claims.pattern.threshold` Fällen. Nur Hinweis — ein Sammelfall
 * oder Regress entsteht nie automatisch.
 */
final class ClaimPatternDetector {
    /** Regel → Gruppenschlüssel je Fall (null = Fall passt nicht zur Regel). */
    public const RULES = ['lot', 'article_defect', 'article_cause', 'supplier_defect', 'entry_type_cause'];

    public const DEFAULT_THRESHOLD = 3;

    public const DEFAULT_WINDOW_DAYS = 90;

    /**
     * @return list<array{rule: string, key: string, label: string, count: int, case_ids: list<int>, case_numbers: list<string>, first_case_id: int}>
     */
    public function detect(int $organizationId, CarbonImmutable $from, CarbonImmutable $to, int $threshold): array {
        $cases = ClaimCase::query()
            ->where('organization_id', $organizationId)
            ->with(['stockLot:id,lot_no', 'article:id,name', 'supplier:id,name', 'defectType:id,label', 'rootCause:id,label', 'diaryEntry:id,entry_type_id', 'diaryEntry.entryType:id,label'])
            ->whereBetween('reported_at', [$from, $to])
            ->orderBy('id')
            ->get();

        $patterns = [];
        foreach (self::RULES as $rule) {
            $cases->groupBy(fn (ClaimCase $case): string => (string) $this->key($rule, $case))
                ->reject(fn (Collection $group, string $key): bool => $key === '' || $group->count() < max(2, $threshold))
                ->each(function (Collection $group, string $key) use ($rule, &$patterns): void {
                    /** @var ClaimCase $first */
                    $first = $group->first();
                    $patterns[] = [
                        'rule' => $rule,
                        'key' => $key,
                        'label' => $this->label($rule, $first),
                        'count' => $group->count(),
                        'case_ids' => array_values($group->pluck('id')->map(static fn ($id): int => (int) $id)->all()),
                        'case_numbers' => array_values($group->pluck('number')->map(static fn ($n): string => (string) $n)->all()),
                        'first_case_id' => (int) $first->id,
                    ];
                });
        }
        usort($patterns, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));

        return $patterns;
    }

    /** @return array{0: int, 1: int} Schwelle und Zeitfenster (Tage) der Organisation */
    public function settingsFor(Organization $organization): array {
        $read = static fn (string $rest, int $fallback): int => (int) (data_get($organization->settings, 'claims.pattern.' . $rest)
            ?? Setting::get('claims.pattern.' . $rest, $fallback));

        return [max(2, $read('threshold', self::DEFAULT_THRESHOLD)), max(7, $read('window_days', self::DEFAULT_WINDOW_DAYS))];
    }

    private function key(string $rule, ClaimCase $case): ?string {
        $join = static fn (?int ...$ids): ?string => in_array(null, $ids, true) ? null : implode(':', $ids);

        return match ($rule) {
            'lot' => $join($case->stock_lot_id),
            'article_defect' => $join($case->article_id, $case->defect_type_classification_id),
            'article_cause' => $join($case->article_id, $case->root_cause_classification_id),
            'supplier_defect' => $join($case->supplier_id, $case->defect_type_classification_id),
            'entry_type_cause' => $join($case->diaryEntry?->entry_type_id, $case->root_cause_classification_id),
            default => null,
        };
    }

    private function label(string $rule, ClaimCase $case): string {
        return implode(' × ', array_filter(match ($rule) {
            'lot' => [$case->stockLot?->lot_no, $case->article?->name],
            'article_defect' => [$case->article?->name, $case->defectType?->label],
            'article_cause' => [$case->article?->name, $case->rootCause?->label],
            'supplier_defect' => [$case->supplier?->name, $case->defectType?->label],
            'entry_type_cause' => [$case->diaryEntry?->entryType?->label, $case->rootCause?->label],
            default => [],
        }));
    }
}
