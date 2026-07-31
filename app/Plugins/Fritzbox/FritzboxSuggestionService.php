<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox;

use App\Models\{Customer, ForeignCustomer, Organization, Project, TimeEntry};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Leichter Vorschlags-Dienst für unbekannte Rufnummern-Gruppen der Inbox
 * (abgespecktes RemoteSupport-Suggester-Muster, zwei Signale):
 *
 * 1. Überlappung — Anrufe der Nummer überschneiden sich mit bereits erfassten
 *    Zeiten eines Kunden (≥ 60 s je Anruf, dominant ab 70 % der gematchten
 *    Anrufe). Wer während der Telefonate gebucht war, ist der wahrscheinliche
 *    Gesprächspartner.
 * 2. Text — der CSV-Anzeigename trifft einen Matchcode exakt bzw. einen
 *    Kunden-/Endkundennamen fuzzy (Schwelle 0.82, nur eindeutige Treffer).
 */
class FritzboxSuggestionService {
    private const SUGGEST_THRESHOLD = 0.82;

    private const MIN_OVERLAP_SECONDS = 60;

    private const DOMINANT_SHARE = 0.7;

    /**
     * @param  iterable<array<string, mixed>>  $groups
     * @return array<string, array{customer_sqid: ?string, foreign_sqid: ?string}>  group_key → Vorschlag
     */
    public function suggestForGroups(Organization $organization, iterable $groups): array {
        $out = [];
        foreach ($groups as $group) {
            $target = $this->overlapTarget($organization, (array) ($group['entries'] ?? []))
                ?? $this->textTarget($organization, (string) ($group['name'] ?? ''));

            if ($target instanceof ForeignCustomer) {
                $out[(string) $group['group_key']] = [
                    'customer_sqid' => $target->customer?->sqid,
                    'foreign_sqid' => (string) $target->sqid,
                ];
            } elseif ($target instanceof Customer) {
                $out[(string) $group['group_key']] = [
                    'customer_sqid' => (string) $target->sqid,
                    'foreign_sqid' => null,
                ];
            }
        }

        return $out;
    }

    /**
     * Signal 1: Zeitüberschneidung der Anrufe mit erfassten Zeiten. Je Anruf
     * gewinnt das Ziel (Kunde bzw. Endkunde) mit der größten Überlappung;
     * dominiert ein Ziel ≥ 70 % der gematchten Anrufe, wird es vorgeschlagen.
     *
     * @param  array<mixed>  $entries
     */
    private function overlapTarget(Organization $organization, array $entries): Customer|ForeignCustomer|null {
        $votes = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            try {
                $start = CarbonImmutable::parse((string) ($entry['started_at'] ?? ''));
                $end = CarbonImmutable::parse((string) ($entry['ended_at'] ?? ''));
            } catch (\Throwable) {
                continue;
            }

            $candidates = TimeEntry::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('started_at', '<', $end)
                ->where('ended_at', '>', $start)
                ->whereNotNull('project_id')
                ->get(['project_id', 'started_at', 'ended_at']);

            $bestKey = null;
            $bestOverlap = 0;
            foreach ($candidates as $candidate) {
                if ($candidate->started_at === null || $candidate->ended_at === null) {
                    continue;
                }
                $overlap = min($candidate->ended_at->getTimestamp(), $end->getTimestamp())
                    - max($candidate->started_at->getTimestamp(), $start->getTimestamp());
                if ($overlap >= self::MIN_OVERLAP_SECONDS && $overlap > $bestOverlap) {
                    $bestOverlap = $overlap;
                    $bestKey = (int) $candidate->project_id;
                }
            }
            if ($bestKey !== null) {
                $votes[$bestKey] = ($votes[$bestKey] ?? 0) + 1;
            }
        }

        if ($votes === []) {
            return null;
        }

        // Projekt-Stimmen auf Kunde/Endkunde aggregieren.
        $projects = Project::query()
            ->withoutGlobalScopes()
            ->whereIn('id', array_keys($votes))
            ->get(['id', 'customer_id', 'foreign_customer_id']);
        $byTarget = [];
        $total = 0;
        foreach ($projects as $project) {
            if ($project->customer_id === null) {
                continue; // interne Projekte sind kein Zuordnungs-Vorschlag
            }
            $key = $project->customer_id . '|' . ($project->foreign_customer_id ?? '');
            $count = $votes[$project->id] ?? 0;
            $byTarget[$key] = ($byTarget[$key] ?? 0) + $count;
            $total += $count;
        }
        if ($byTarget === [] || $total === 0) {
            return null;
        }

        arsort($byTarget);
        $dominantKey = (string) array_key_first($byTarget);
        if ($byTarget[$dominantKey] / $total < self::DOMINANT_SHARE) {
            return null;
        }

        [$customerId, $foreignId] = explode('|', $dominantKey);
        if ($foreignId !== '') {
            return ForeignCustomer::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)->find((int) $foreignId);
        }

        return Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)->find((int) $customerId);
    }

    /** Signal 2: Matchcode exakt, sonst Namens-Fuzzy (nur eindeutige Treffer). */
    private function textTarget(Organization $organization, string $name): Customer|ForeignCustomer|null {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $customers = Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)->whereNull('archived_at')
            ->get(['id', 'name', 'company', 'matchcode']);
        $foreigns = ForeignCustomer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)->whereNull('archived_at')
            ->get(['id', 'customer_id', 'name', 'company', 'matchcode']);

        // Matchcode-Treffer (Kunde vor Endkunde, wie im RemoteSupport-Suggester).
        $upper = mb_strtoupper($name);
        foreach ([$customers, $foreigns] as $pool) {
            foreach ($pool as $candidate) {
                if ($candidate->matchcode !== null && mb_strtoupper((string) $candidate->matchcode) === $upper) {
                    return $candidate;
                }
            }
        }

        $best = null;
        $bestScore = 0.0;
        $ambiguous = false;
        foreach ([$customers, $foreigns] as $pool) {
            foreach ($pool as $candidate) {
                $score = max(
                    $this->similarity($name, (string) $candidate->name),
                    $this->similarity($name, (string) $candidate->company),
                );
                if ($score < self::SUGGEST_THRESHOLD) {
                    continue;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                    $ambiguous = false;
                } elseif (abs($score - $bestScore) < 0.0001 && ! $candidate->is($best instanceof Model ? $best : null)) {
                    $ambiguous = true;
                }
            }
        }

        return $ambiguous ? null : $best;
    }

    private function similarity(string $a, string $b): float {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
