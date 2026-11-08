<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangelogCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Feature-Register ⇒ CHANGELOG“ (Vollscan 2026-11-02, gap-01;
 * Wiederkehr von lk-07): Der Release-Prozess verlangt je Änderung einen Eintrag
 * unter `[Unreleased]`, trotzdem fehlten nach neun Phasen 192 MVP-Nummern.
 *
 * Regel: Jede MVP-Nummer, die `features/README.md` des Architecture-Repos in
 * der Statusspalte mit ✅ führt, steht im CHANGELOG — einzeln (`MVP-1256`), als
 * Liste (`MVP-1275, 1298, 1300`) oder als Spanne (`MVP-1256–1261`). Fehlt das
 * Schwester-Repo (CI, frischer Klon), gibt es nichts zu prüfen. Die Baseline
 * nennt Altbestand vor der Einführung des Gates und darf nur schrumpfen.
 */
class ChangelogCoverageRuleTest extends TestCase {
    use ScansSourceTree;

    private const string REGISTER = '/../WorkDiary-Architecture/features/README.md';

    /**
     * Nummern, die beim Einführen des Gates (2026-11-02) im CHANGELOG fehlten:
     * Phasen 45–125 (Juli bis September 2026), deren Einträge ohne MVP-Nummer
     * geschrieben wurden; die Umsetzungsberichte liegen in der Chronik. Nur
     * schrumpfend — wer die Nummer am Eintrag nachträgt, streicht sie hier.
     *
     * @var list<int>
     */
    private const array BASELINE = [
        117, 119, 437, 457, 458, 499, 500, 501, 502,
        542, 543, 544, 545, 546, 547, 548, 549, 550, 551, 552, 553, 554, 555, 556, 557, 558, 559, 560, 561, 562, 563, 564, 565, 566, 567,
        600, 601, 602, 603, 604, 605, 606, 607, 608, 609, 610, 611, 612, 613, 650, 651, 652, 653,
        689, 691, 692, 693, 694, 695, 696, 697, 698, 699, 700, 701, 702, 703, 704, 705, 708, 709, 711, 713, 715, 716, 719,
        727, 728, 729, 730, 732, 758, 759, 760, 761, 762, 763, 764, 765, 766, 767, 768, 769, 770, 771, 772, 773, 774, 775, 776, 777,
        817, 818, 819, 820, 821, 822, 831, 832, 833,
        860, 861, 862, 863, 864, 865, 866, 867, 868, 869, 870, 871, 872, 873, 874, 875, 876, 877, 878, 879,
        988, 989, 1024, 1025,
    ];

    public function test_every_checked_mvp_number_of_the_register_is_in_the_changelog(): void {
        $register = $this->repoRoot() . self::REGISTER;
        if (! is_file($register)) {
            $this->addToAssertionCount(1);

            return;
        }

        $expected = $this->checkedNumbers((string) file_get_contents($register));
        $this->assertNotSame([], $expected, 'Das Feature-Register nennt keine ✅-MVP-Nummer — Parser prüfen.');

        $documented = $this->changelogNumbers();
        $missing = array_values(array_diff($expected, $documented, self::BASELINE));
        sort($missing);

        $this->assertSame([], $missing, "Im Feature-Register als umgesetzt (✅) geführt, aber ohne Eintrag in CHANGELOG.md:\n"
            . implode(', ', array_map(static fn (int $n): string => 'MVP-' . $n, $missing))
            . "\n\nBlock unter [Unreleased] aus dem Umsetzungsbericht der Phase ergänzen (Nummern einzeln oder als Spanne).");
    }

    public function test_baseline_only_shrinks(): void {
        $documented = $this->changelogNumbers();
        $stale = array_values(array_intersect(self::BASELINE, $documented));

        $this->assertSame([], $stale, 'Inzwischen im CHANGELOG — aus der BASELINE streichen: '
            . implode(', ', array_map(static fn (int $n): string => 'MVP-' . $n, $stale)));
    }

    public function test_parser_reads_single_numbers_lists_and_ranges(): void {
        $this->assertSame([1074, 1075, 1076, 1077, 1078], $this->numbersIn('Done (Phase 132: MVP-1074–1077 ✅ 2026-10-07; Phase 133 MVP-1078 ✅; Pilot offen)'));
        $this->assertSame([1275, 1298, 1300], $this->numbersIn('(Phase 156, MVP-1275, 1298, 1300; erechnung-toolkit 0.20)'));
        $this->assertSame([874, 875, 879], $this->numbersIn('Basiszins-Feed ✅ MVP-874/875/879 2026-09-25'));
        $this->assertSame([1274, 1277, 1278, 1279], $this->numbersIn('Phase 156 ✅ MVP-1274, MVP-1277–1279 2026-10-23'));
        $this->assertSame([], $this->checkedNumbers('| P1 | [X](./001-x.md) | In Progress (MVP-1 geplant) | … |'), 'ohne ✅ zählt nichts');
        $this->assertSame([5, 6], $this->checkedNumbers("| P1 | [X](./001-x.md) | Done (MVP-5 ✅; MVP-7 offen); MVP-6 ✅ | … |\n"), 'nur Segmente mit ✅');
        $this->assertSame([1304, 1305, 1306, 1307, 1308, 1309, 1310, 1311, 1312], $this->checkedNumbers('| P1 | [X](./169-x.md) | Done (Phase 157: MVP-1304–1312 ✅ 2026-10-23, externer Pilot MVP-1313 offen; [Bericht](x.md)) | … |'), 'offener Teilsatz hinter dem Komma zählt nicht');
        $this->assertSame([1280, 1281], $this->checkedNumbers('| P1 | [X](./066-x.md) | Done; Phase 156 ✅ MVP-1280, MVP-1281 2026-10-22 | … |'), 'Liste hinter dem Haken bleibt vollständig');
    }

    /**
     * ✅-Nummern der Statusspalte: je Registerzeile wird die Spalte in Segmente
     * (`;`) zerlegt; nur Segmente mit Haken zählen, damit „MVP-7 offen“ neben
     * einem erledigten Punkt nicht mitgelesen wird.
     *
     * @return list<int>
     */
    private function checkedNumbers(string $register): array {
        $numbers = [];
        // Kein `\R` ohne `/u`: das Byte 0x85 (NEL) steckt im ✅ (E2 9C 85) und zerlegte die Zeile mitten im Haken.
        foreach (preg_split('/\r\n|\r|\n/', $register) ?: [] as $line) {
            if (preg_match('/^\|\s*P\d\s*\|[^|]*\|([^|]*)\|/u', $line, $m) !== 1) {
                continue;
            }
            foreach (explode(';', $m[1]) as $segment) {
                if (! str_contains($segment, '✅')) {
                    continue;
                }
                // „MVP-1304–1312 ✅ 2026-10-23, externer Pilot MVP-1313 offen“: der offene
                // Teil hängt per Komma am erledigten — Teilsätze mit Restmarker zählen nicht.
                foreach (explode(',', $segment) as $clause) {
                    if (preg_match('/\b(offen|geplant|blockiert|reserviert|verworfen|zurückgestellt)\b/u', $clause) === 1) {
                        continue;
                    }
                    $numbers = [...$numbers, ...$this->numbersIn($clause)];
                }
            }
        }
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }

    /** @return list<int> */
    private function changelogNumbers(): array {
        return $this->numbersIn((string) file_get_contents($this->repoRoot() . '/CHANGELOG.md'));
    }

    /**
     * `MVP-1256`, `MVP-1256–1261` (Spanne mit Halbgeviert- oder Bindestrich), Listen
     * `MVP-1275, 1298, 1300` und `MVP-874/875/879`; Folgezahlen ohne Präfix nur
     * bis vier Stellen und nicht vor einem Bindestrich (Datum `2026-10-07`).
     *
     * @return list<int>
     */
    private function numbersIn(string $text): array {
        $numbers = [];
        preg_match_all('/MVP-(\d{1,4})(?:[–-](\d{1,4}))?((?:\s*[,\/]\s*(?:MVP-)?\d{1,4}(?:[–-]\d{1,4})?(?![\d-]))*)/u', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $numbers = [...$numbers, ...range((int) $m[1], (int) ($m[2] !== '' ? $m[2] : $m[1]))];
            preg_match_all('/(\d{1,4})(?:[–-](\d{1,4}))?/u', $m[3], $tail, PREG_SET_ORDER);
            foreach ($tail as $t) {
                $numbers = [...$numbers, ...range((int) $t[1], (int) (($t[2] ?? '') !== '' ? $t[2] : $t[1]))];
            }
        }
        $numbers = array_values(array_unique(array_filter($numbers, static fn (int $n): bool => $n > 0)));
        sort($numbers);

        return $numbers;
    }
}
