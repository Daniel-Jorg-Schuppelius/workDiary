<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningOutlineParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use Illuminate\Support\Str;

/**
 * Gliederungstext (KI-Entwurf oder von Hand) in Abschnitte und Einheiten
 * übersetzen (Feature 149, MVP-781). Tolerant gegenüber Markdown-Überschriften,
 * Nummerierung und Aufzählungszeichen — der Entwurf kommt aus einem
 * Sprachmodell, nicht aus einem Formular.
 *
 *  - Abschnitt: `# Titel`, `1. Titel`, `1) Titel`, `Abschnitt 1: Titel`
 *  - Einheit: `- Titel`, `* Titel`, `• Titel`, `1.1 Titel`
 *  - alles andere wird übergangen (Lernziele, Fließtext)
 *
 * Rein, ohne DB — das Anlegen übernimmt der Aufrufer über den
 * {@see LearningCourseService}.
 */
class LearningOutlineParser {
    public const MAX_SECTIONS = 20;

    public const MAX_UNITS = 100;

    private const TITLE_LIMIT = 180;

    /**
     * @return list<array{title: string, units: list<string>}>
     */
    public function parse(string $text): array {
        /** @var list<string> $titles */
        $titles = [];
        /** @var list<list<string>> $unitsBySection */
        $unitsBySection = [];
        $current = null;
        $units = 0;

        foreach (preg_split('/\R/', $text) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(?:#{1,3}\s*|(?:abschnitt|section|teil|modul|kapitel)\s*\d+\s*[:.)\-–]\s*|\d+[.)]\s+)(.+)$/iu', $line, $m) === 1
                && preg_match('/^\d+\.\d+/', $line) !== 1) {
                if (count($titles) >= self::MAX_SECTIONS) {
                    break;
                }
                $titles[] = $this->title($m[1]);
                $unitsBySection[] = [];
                $current = count($titles) - 1;

                continue;
            }

            if (preg_match('/^(?:[-*•]\s+|\d+\.\d+[.)]?\s+)(.+)$/u', $line, $m) === 1) {
                if ($units >= self::MAX_UNITS) {
                    break;
                }
                if ($current === null) {
                    // Einheiten vor dem ersten Abschnitt: ein namenloser
                    // Abschnitt wäre eine Lüge — sie kommen ohne Abschnitt.
                    $titles[] = '';
                    $unitsBySection[] = [];
                    $current = 0;
                }
                $unitsBySection[$current][] = $this->title($m[1]);
                $units++;
            }
        }

        $sections = [];
        foreach ($titles as $index => $title) {
            $list = $unitsBySection[$index] ?? [];
            if ($title === '' && $list === []) {
                continue;
            }
            $sections[] = ['title' => $title, 'units' => $list];
        }

        return $sections;
    }

    /** Titel ohne Markdown-Fett, ohne nachgestelltes Lernziel („ — …" / „: …"). */
    private function title(string $raw): string {
        $clean = trim(str_replace(['**', '__'], '', $raw));
        $clean = (string) preg_replace('/\s+[—–-]\s+.*$/u', '', $clean);

        return Str::limit(trim($clean, " \t:."), self::TITLE_LIMIT, '');
    }
}
