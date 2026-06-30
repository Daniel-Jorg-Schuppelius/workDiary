<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\{Organization, Project, ProjectMergeDismissal};
use App\Services\Integration\Match\{EntityMatcher, MatchStrategy};
use App\Services\Integration\Profiles\ProjectMatchProfile;
use Illuminate\Support\Collection;

/**
 * Findet Dubletten-Kandidaten unter den Projekten einer Organisation (z. B.
 * mehrfach angelegte „Wartung"-Projekte nach dem Toggl-Import). Vergleiche
 * laufen ausschließlich innerhalb desselben Kunden (inkl. „ohne Kunde"), damit
 * gleichnamige Projekte verschiedener Kunden nicht fälschlich als Dublette
 * erscheinen — und das Zusammenführen kundenkonsistent bleibt.
 */
class ProjectDuplicateFinder {
    public const CONF_EXACT = MatchStrategy::EXACT;
    public const CONF_LIKELY = MatchStrategy::LIKELY;
    public const CONF_FUZZY = MatchStrategy::FUZZY;

    /** @var array<string, int> */
    private const RANK = [
        MatchStrategy::FUZZY => 1,
        MatchStrategy::LIKELY => 2,
        MatchStrategy::EXACT => 3,
    ];

    public function __construct(
        private readonly EntityMatcher $matcher,
        private readonly ProjectMatchProfile $profile,
    ) {}

    /**
     * @param  string|null  $onlyConfidence  Auf eine Stufe einschränken (z. B. nur 'exact').
     * @return Collection<int, array{source: Project, target: Project, confidence: string, reasons: list<string>}>
     */
    public function candidates(Organization $organization, ?string $onlyConfidence = null): Collection {
        /** @var Collection<int, Project> $projects */
        $projects = $this->profile->candidates($organization)
            ->withCount(['diaryEntries', 'timeEntries'])
            ->get();

        $dismissed = $this->dismissedKeys($organization);

        /** @var array<string, array{source: Project, target: Project, confidence: string, reasons: list<string>}> $pairs */
        $pairs = [];

        // Nur innerhalb desselben Kunden vergleichen (null => Gruppe "0").
        foreach ($projects->groupBy(fn(Project $p): int => (int) ($p->customer_id ?? 0)) as $group) {
            $list = $group->values()->all();
            $count = count($list);

            $fields = [];
            foreach ($list as $i => $project) {
                $fields[$i] = $this->profile->extract($project);
            }

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $list[$i];
                    $b = $list[$j];

                    // Direkt verwandte Projekte (Eltern/Kind) sind nicht
                    // zusammenführbar — gar nicht erst vorschlagen.
                    if ((int) $a->parent_id === (int) $b->id || (int) $b->parent_id === (int) $a->id) {
                        continue;
                    }

                    $cmp = $this->matcher->compare($this->profile, $fields[$i], $fields[$j]);
                    if ($cmp['confidence'] === null) {
                        continue;
                    }

                    $key = min((int) $a->id, (int) $b->id) . '-' . max((int) $a->id, (int) $b->id);
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
     * Bestimmt Ziel (bleibt) und Quelle (geht auf): Standardprojekt > mehr
     * verknüpfte Einträge (Zeiten + Aufträge) > kleinere (ältere) ID.
     *
     * @return array{0: Project, 1: Project}  [Ziel, Quelle]
     */
    private function orderTargetSource(Project $a, Project $b): array {
        $score = static function (Project $c): array {
            $entries = (int) ($c->diary_entries_count ?? 0) + (int) ($c->time_entries_count ?? 0);

            return [$c->is_default ? 1 : 0, $entries, -((int) $c->id)];
        };

        return $score($a) >= $score($b) ? [$a, $b] : [$b, $a];
    }

    /**
     * @return array<string, true>
     */
    private function dismissedKeys(Organization $organization): array {
        return ProjectMergeDismissal::query()
            ->where('organization_id', $organization->id)
            ->get(['project_low_id', 'project_high_id'])
            ->mapWithKeys(fn(ProjectMergeDismissal $d): array => [
                $d->project_low_id . '-' . $d->project_high_id => true,
            ])
            ->all();
    }
}
