<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkInstructionParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Enums\Procedure\ProcedureStepType;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Schrittvorschläge aus dem Text einer Arbeitsanweisung (MVP-913):
 * nummerierte Zeilen („1.“, „2)“, „Schritt 3:“) oder Aufzählungen beginnen
 * einen Schritt, Folgezeilen werden zur Beschreibung. Ohne solche Marken wird
 * jede Zeile ein Schritt. Die erste Zeile vor dem ersten Schritt gilt als
 * Titel. Schrittart aus Stichwörtern (Foto, Unterschrift, Messwert), sonst
 * Bestätigung — die Person prüft alles in der Vorschau.
 */
final class WorkInstructionParser {
    public const MAX_STEPS = 60;

    private const LABEL_MAX = 180;

    /**
     * @return array{title: ?string, steps: list<array{label: string, description: ?string, step_type: string}>}
     */
    public function parse(string $text): array {
        $lines = array_values(array_filter(array_map(static fn (string $l): string => StringHelper::normalizeWhitespace($l, true), preg_split('/\R/u', $text) ?: []), static fn (string $l): bool => $l !== ''));
        $marker = '/^(?:schritt\s*)?(?:\d{1,3}[.):]|[-•*–])\s*(.+)$/iu';
        $hasMarkers = count(array_filter($lines, static fn (string $l): bool => preg_match($marker, $l) === 1)) >= 2;

        $title = null;
        /** @var list<string> $labels */
        $labels = [];
        /** @var list<list<string>> $details */
        $details = [];
        foreach ($lines as $line) {
            if ($hasMarkers) {
                if (preg_match($marker, $line, $m) === 1) {
                    $labels[] = $m[1];
                    $details[] = [];
                } elseif ($labels === []) {
                    $title ??= $line;
                } else {
                    $details[count($details) - 1][] = $line;
                }
            } elseif ($title === null && count($lines) > 1) {
                $title = $line;
            } else {
                $labels[] = $line;
                $details[] = [];
            }
        }

        $steps = [];
        foreach (array_slice($labels, 0, self::MAX_STEPS) as $index => $label) {
            $description = $details[$index];
            if (mb_strlen($label) > self::LABEL_MAX) {
                array_unshift($description, $label);
                $label = mb_substr($label, 0, self::LABEL_MAX - 1) . '…';
            }
            $steps[] = [
                'label' => $label,
                'description' => $description === [] ? null : implode("\n", $description),
                'step_type' => $this->typeFor($label . ' ' . implode(' ', $description))->value,
            ];
        }

        return ['title' => $title, 'steps' => $steps];
    }

    private function typeFor(string $text): ProcedureStepType {
        $text = mb_strtolower($text);

        return match (true) {
            preg_match('/\b(foto|fotografieren|bild)\w*/u', $text) === 1 => ProcedureStepType::Photo,
            preg_match('/\b(unterschrift|unterschreiben|abzeichnen|gegenzeichnen)\w*/u', $text) === 1 => ProcedureStepType::Signature,
            preg_match('/\b(messen|messwert|ablesen|temperatur|druck prüfen)\w*/u', $text) === 1 => ProcedureStepType::Number,
            default => ProcedureStepType::Confirm,
        };
    }
}
