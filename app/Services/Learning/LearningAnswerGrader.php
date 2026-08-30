<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAnswerGrader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningQuestionKind;

/**
 * Bewertet eine Antwort gegen die **eingefrorene** Frage aus der
 * Prüfungsakte (Feature 149, MVP-738).
 *
 * Reine Rechenlogik ohne Datenbankzugriff — dadurch isoliert testbar, wie
 * der {@see \App\Services\Compliance\AttendanceComplianceChecker}. Bewertet
 * wird IMMER gegen den Snapshot des Versuchs, nie gegen die aktuelle
 * Frage: sonst änderte eine spätere Korrektur rückwirkend alte Ergebnisse.
 *
 * Der Aufsatz wird hier nicht bewertet (`correct = null`) — dafür braucht
 * es einen Menschen.
 */
class LearningAnswerGrader {
    /**
     * @param  array<string, mixed>  $question  Frage aus dem Snapshot
     * @param  array<string, mixed>|null  $payload  Antwort der lernenden Person
     * @return array{correct: bool|null, points: int}
     */
    public function grade(array $question, ?array $payload): array {
        $kind = LearningQuestionKind::tryFrom((string) ($question['kind'] ?? ''));
        $max = max(0, (int) ($question['points'] ?? 0));

        if ($kind === null || $kind === LearningQuestionKind::Essay) {
            return ['correct' => null, 'points' => 0];
        }

        if ($payload === null) {
            return ['correct' => false, 'points' => 0];
        }

        return match ($kind) {
            LearningQuestionKind::Single,
            LearningQuestionKind::TrueFalse => $this->gradeSingle($question, $payload, $max),
            LearningQuestionKind::Multiple => $this->gradeMultiple($question, $payload, $max),
            LearningQuestionKind::ShortText => $this->gradeShortText($question, $payload, $max),
            LearningQuestionKind::Cloze => $this->gradeCloze($question, $payload, $max),
            LearningQuestionKind::Sort => $this->gradeSort($question, $payload, $max),
            LearningQuestionKind::Matching => $this->gradeMatching($question, $payload, $max),
            LearningQuestionKind::Hotspot => $this->gradeHotspot($question, $payload, $max),
            LearningQuestionKind::Matrix => $this->gradeMatrix($question, $payload, $max),
        };
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeSingle(array $question, array $payload, int $max): array {
        $chosen = $this->intList($payload['option_ids'] ?? []);
        $correct = $this->correctOptionIds($question);

        $isCorrect = count($chosen) === 1 && count($correct) === 1 && $chosen[0] === $correct[0];

        return ['correct' => $isCorrect, 'points' => $isCorrect ? $max : 0];
    }

    /**
     * Mehrfachauswahl: standardmäßig alles-oder-nichts. Mit
     * `settings.partial_credit` zählt jede richtig getroffene Option, jede
     * falsch gesetzte zieht ab (nie unter null) — die faire Variante bei
     * vielen Optionen.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeMultiple(array $question, array $payload, int $max): array {
        $chosen = $this->intList($payload['option_ids'] ?? []);
        $correct = $this->correctOptionIds($question);
        $all = $this->allOptionIds($question);

        $exact = $this->sameSet($chosen, $correct);
        $partial = (bool) ($question['settings']['partial_credit'] ?? false);

        if (! $partial || $correct === []) {
            return ['correct' => $exact, 'points' => $exact ? $max : 0];
        }

        $hits = count(array_intersect($chosen, $correct));
        $wrong = count(array_diff($chosen, $correct));
        $wrongPool = max(1, count(array_diff($all, $correct)));

        $ratio = ($hits / count($correct)) - ($wrong / $wrongPool);
        $ratio = max(0.0, min(1.0, $ratio));

        return ['correct' => $exact, 'points' => (int) round($max * $ratio)];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeShortText(array $question, array $payload, int $max): array {
        $given = trim((string) ($payload['text'] ?? ''));
        $accepted = $question['settings']['answers'] ?? [];
        $caseSensitive = (bool) ($question['settings']['case_sensitive'] ?? false);

        $isCorrect = $this->matchesAny($given, is_array($accepted) ? array_values($accepted) : [], $caseSensitive);

        return ['correct' => $isCorrect, 'points' => $isCorrect ? $max : 0];
    }

    /**
     * Lückentext: Teilpunkte je richtig gefüllter Lücke — ein Tippfehler in
     * Lücke 3 soll nicht die ganze Frage kosten.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeCloze(array $question, array $payload, int $max): array {
        $gaps = $question['settings']['gaps'] ?? [];
        $gaps = is_array($gaps) ? array_values($gaps) : [];
        $given = $payload['gaps'] ?? [];
        $given = is_array($given) ? array_values($given) : [];
        $caseSensitive = (bool) ($question['settings']['case_sensitive'] ?? false);

        if ($gaps === []) {
            return ['correct' => false, 'points' => 0];
        }

        $hits = 0;
        foreach ($gaps as $index => $accepted) {
            $value = trim((string) ($given[$index] ?? ''));
            if ($this->matchesAny($value, is_array($accepted) ? array_values($accepted) : [$accepted], $caseSensitive)) {
                $hits++;
            }
        }

        $ratio = $hits / count($gaps);

        return ['correct' => $hits === count($gaps), 'points' => (int) round($max * $ratio)];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeSort(array $question, array $payload, int $max): array {
        // NICHT intList(): das sortiert — bei einer Sortierfrage ist die
        // Reihenfolge genau die Information, die geprüft wird.
        $given = $this->intSequence($payload['order'] ?? []);
        $expected = $this->orderedOptionIds($question);

        $isCorrect = $given === $expected && $expected !== [];

        return ['correct' => $isCorrect, 'points' => $isCorrect ? $max : 0];
    }

    /**
     * Zuordnung: Optionen mit gleichem `match_key` gehören zusammen.
     * Teilpunkte je richtiges Paar.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeMatching(array $question, array $payload, int $max): array {
        $pairs = $payload['pairs'] ?? [];
        $pairs = is_array($pairs) ? $pairs : [];

        $keyById = [];
        foreach ($this->options($question) as $option) {
            $keyById[(int) ($option['id'] ?? 0)] = (string) ($option['match_key'] ?? '');
        }

        $expectedPairs = 0;
        $keyCounts = array_count_values(array_filter($keyById, static fn (string $key): bool => $key !== ''));
        foreach ($keyCounts as $count) {
            if ($count >= 2) {
                $expectedPairs++;
            }
        }

        if ($expectedPairs === 0) {
            return ['correct' => false, 'points' => 0];
        }

        $hits = 0;
        foreach ($pairs as $left => $right) {
            $leftKey = $keyById[(int) $left] ?? null;
            $rightKey = $keyById[(int) $right] ?? null;
            if ($leftKey !== null && $leftKey !== '' && $leftKey === $rightKey) {
                $hits++;
            }
        }

        $ratio = min(1.0, $hits / $expectedPairs);

        return ['correct' => $hits === $expectedPairs, 'points' => (int) round($max * $ratio)];
    }

    /**
     * Bildmarkierung: getroffen, wenn der Klick in einem der hinterlegten
     * Bereiche liegt.
     *
     * Koordinaten sind **Prozent** der Bildkante, nicht Pixel — sonst wäre
     * die Antwort von der Anzeigegröße abhängig und auf dem Telefon eine
     * andere als am Bildschirm.
     *
     * Alles oder nichts: ein halber Treffer ist kein Treffer.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeHotspot(array $question, array $payload, int $max): array {
        $spots = $question['settings']['hotspots'] ?? [];
        $spots = is_array($spots) ? array_values($spots) : [];

        if ($spots === []) {
            return ['correct' => false, 'points' => 0];
        }

        // Zwei Wege zur selben Antwort: Klick ins Bild (Maus/Touch) oder
        // Auswahl der Fläche aus der Liste (Tastatur, WCAG 2.1.1). Beide
        // zählen nur, wenn die getroffene Fläche als richtig hinterlegt ist.
        $chosen = $payload['spot'] ?? null;

        if ($chosen !== null && $chosen !== '' && isset($spots[(int) $chosen])) {
            $spot = $spots[(int) $chosen];
            $hit = is_array($spot) && (bool) ($spot['is_correct'] ?? false);

            return ['correct' => $hit, 'points' => $hit ? $max : 0];
        }

        $x = $payload['x'] ?? null;
        $y = $payload['y'] ?? null;

        if (! is_numeric($x) || ! is_numeric($y)) {
            return ['correct' => false, 'points' => 0];
        }

        $x = (float) $x;
        $y = (float) $y;

        foreach ($spots as $spot) {
            if (! is_array($spot) || ! (bool) ($spot['is_correct'] ?? false)) {
                continue;
            }

            $left = (float) ($spot['x'] ?? 0);
            $top = (float) ($spot['y'] ?? 0);
            $width = (float) ($spot['w'] ?? 0);
            $height = (float) ($spot['h'] ?? 0);

            if ($width <= 0 || $height <= 0) {
                continue;
            }

            if ($x >= $left && $x <= $left + $width && $y >= $top && $y <= $top + $height) {
                return ['correct' => true, 'points' => $max];
            }
        }

        return ['correct' => false, 'points' => 0];
    }

    /**
     * Matrix: jede Zeile gehört in genau eine Spalte — anders als bei der
     * Zuordnung darf **dieselbe Spalte mehrfach** vorkommen („welcher Stoff
     * gehört in welche Brandklasse").
     *
     * Teilpunkte je richtiger Zeile: eine falsche Zuordnung soll nicht die
     * ganze Frage kosten.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $payload
     * @return array{correct: bool|null, points: int}
     */
    private function gradeMatrix(array $question, array $payload, int $max): array {
        $rows = $question['settings']['rows'] ?? [];
        $rows = is_array($rows) ? array_values($rows) : [];

        if ($rows === []) {
            return ['correct' => false, 'points' => 0];
        }

        $given = $payload['matrix'] ?? [];
        $given = is_array($given) ? $given : [];

        $hits = 0;

        foreach ($rows as $index => $row) {
            $expected = is_array($row) ? ($row['column'] ?? null) : null;

            if ($expected === null) {
                continue;
            }

            $chosen = $given[$index] ?? null;

            if ($chosen !== null && (int) $chosen === (int) $expected) {
                $hits++;
            }
        }

        $ratio = $hits / count($rows);

        return ['correct' => $hits === count($rows), 'points' => (int) round($max * $ratio)];
    }

    /**
     * @param  list<mixed>  $accepted
     */
    private function matchesAny(string $given, array $accepted, bool $caseSensitive): bool {
        if ($given === '') {
            return false;
        }

        foreach ($accepted as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $matches = $caseSensitive
                ? $given === $candidate
                : mb_strtolower($given) === mb_strtolower($candidate);

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return list<array<string, mixed>>
     */
    private function options(array $question): array {
        $options = $question['options'] ?? [];

        return is_array($options) ? array_values($options) : [];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return list<int>
     */
    private function correctOptionIds(array $question): array {
        $ids = [];
        foreach ($this->options($question) as $option) {
            if ((bool) ($option['is_correct'] ?? false)) {
                $ids[] = (int) ($option['id'] ?? 0);
            }
        }
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return list<int>
     */
    private function allOptionIds(array $question): array {
        $ids = array_map(static fn (array $o): int => (int) ($o['id'] ?? 0), $this->options($question));
        sort($ids);

        return $ids;
    }

    /**
     * Erwartete Reihenfolge einer Sortierfrage: die Optionen nach ihrer
     * gepflegten Position.
     *
     * @param  array<string, mixed>  $question
     * @return list<int>
     */
    private function orderedOptionIds(array $question): array {
        $options = $this->options($question);
        usort($options, static fn (array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));

        return array_map(static fn (array $o): int => (int) ($o['id'] ?? 0), $options);
    }

    /**
     * Zahlenfolge in gegebener Reihenfolge (für Sortierfragen).
     *
     * @return list<int>
     */
    private function intSequence(mixed $values): array {
        $values = is_array($values) ? $values : [$values];

        return array_values(array_map(static fn (mixed $v): int => (int) $v, $values));
    }

    /**
     * Menge von IDs, sortiert — für Vergleiche, bei denen die Reihenfolge
     * keine Rolle spielt (Einfach-/Mehrfachauswahl).
     *
     * @return list<int>
     */
    private function intList(mixed $values): array {
        $values = is_array($values) ? $values : [$values];
        $ids = array_values(array_unique(array_map(static fn (mixed $v): int => (int) $v, $values)));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    private function sameSet(array $a, array $b): bool {
        return $a === $b;
    }
}
