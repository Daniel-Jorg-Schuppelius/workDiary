<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityInspector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase};
use App\Models\Diary\DiaryEntry;
use Illuminate\Support\Str;

/**
 * Leitet sichtbare Datenqualitäts-Hinweise (Feature 024) ohne neue
 * Pflichtmechanik ab: nutzt die bereits vorhandene Pflichtklassifikations-
 * Logik ({@see ClassificationRequirementValidator}) und füttert sie mit den
 * Werten, die ein Auftrag heute persistiert (entry_type über die Auftragsart,
 * priority über die Spalte). Daraus entstehen rein lesende Lücken-Hinweise
 * für die Detailansicht — es wird nichts erzwungen oder geschrieben.
 */
class DataQualityInspector {
    public function __construct(
        private readonly ClassificationRequirementValidator $validator,
    ) {}

    /**
     * Liefert die fehlenden Pflichtklassifikationen eines Auftrags für die
     * angegebene Phase (Default: bei Anlage). Jeder Eintrag enthält die
     * Domäne, ein menschenlesbares Label und die Schwere (hard/soft).
     *
     * @return list<array{domain: string, label: string, severity: string, blocking: bool}>
     */
    public function diaryEntryGaps(
        DiaryEntry $entry,
        ClassificationRequirementPhase $phase = ClassificationRequirementPhase::OnCreate,
    ): array {
        $results = $this->validator->validate($entry, $phase, $this->validator->valuesFor($entry), audit: false);

        $gaps = [];
        foreach ($results as $result) {
            $gaps[] = [
                'domain' => $result->requiredDomain,
                'label' => $this->domainLabel($result->requiredDomain),
                'severity' => $result->severity->value,
                'blocking' => $result->isBlocking(),
            ];
        }

        return $gaps;
    }

    /**
     * Datenqualitäts-Report (Feature 024 → Rang 57): sammelt für die übergebenen
     * Aufträge die fehlenden Pflichtklassifikationen über ALLE Phasen und
     * aggregiert sie nach Domäne, Phase und Schwere. Reines Lesen — es wird
     * nichts erzwungen. Nur Aufträge MIT Lücke landen in `rows`.
     *
     * @param  iterable<DiaryEntry>  $entries
     * @return array{
     *   rows: list<array{id:int, sqid:string, title:string, date:string|null, gaps: list<array{domain:string, label:string, severity:string, blocking:bool, phase:string}>}>,
     *   by_domain: array<string, array{label:string, count:int}>,
     *   by_severity: array<string, int>,
     *   by_phase: array<string, int>,
     *   entries_with_gaps: int
     * }
     */
    public function report(iterable $entries): array {
        $rows = [];
        /** @var array<string, array{label:string, count:int}> $byDomain */
        $byDomain = [];
        /** @var array<string, int> $bySeverity */
        $bySeverity = [];
        /** @var array<string, int> $byPhase */
        $byPhase = [];

        foreach ($entries as $entry) {
            $entryGaps = [];
            foreach (ClassificationRequirementPhase::cases() as $phase) {
                foreach ($this->diaryEntryGaps($entry, $phase) as $gap) {
                    $gap['phase'] = $phase->value;
                    $entryGaps[] = $gap;

                    $byDomain[$gap['domain']] ??= ['label' => $gap['label'], 'count' => 0];
                    $byDomain[$gap['domain']]['count']++;
                    $bySeverity[$gap['severity']] = ($bySeverity[$gap['severity']] ?? 0) + 1;
                    $byPhase[$phase->value] = ($byPhase[$phase->value] ?? 0) + 1;
                }
            }

            if ($entryGaps === []) {
                continue;
            }

            $rows[] = [
                'id' => (int) $entry->id,
                'sqid' => (string) $entry->getRouteKey(),
                'title' => $this->entryTitle($entry),
                'date' => $entry->start_at?->toDateString(),
                'gaps' => $entryGaps,
            ];
        }

        uasort($byDomain, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'rows' => $rows,
            'by_domain' => $byDomain,
            'by_severity' => $bySeverity,
            'by_phase' => $byPhase,
            'entries_with_gaps' => count($rows),
        ];
    }

    private function entryTitle(DiaryEntry $entry): string {
        $title = is_string($entry->title) ? trim($entry->title) : '';
        if ($title !== '') {
            return $title;
        }

        return Str::limit((string) $entry->content, 60);
    }

    /**
     * Bildet die heute am Auftrag persistierten Klassifikationswerte auf das
     * vom Validator erwartete `valuesByDomain`-Format ab. Bewusst konservativ:
     * nur tatsächlich gespeicherte Werte (kein Erfinden von Persistenz).
     *
     * @return array<string, list<string>>
     */
    /** Beschriftung kommt vom Enum — hier keine zweite Tabelle pflegen. */
    private function domainLabel(string $domain): string {
        return ClassificationDomain::tryFrom($domain)?->label() ?? $domain;
    }
}
