<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateFingerprint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use PDFToolkit\Entities\WordBox;

/**
 * Wiedererkennung einer Belegform (Phase 151, E3): Wörter aus dem oberen und
 * unteren Fünftel der ersten Seite, ohne Zahlen und Datumsangaben, in
 * Kleinschreibung. Kopf (Firma, Anschrift) und Fuß (Bank, Register) sind je
 * Lieferant stabil, Nummern und Beträge nicht. Ähnlichkeit nach Jaccard.
 */
final class TemplateFingerprint {
    public const MIN_SIMILARITY = 0.6;

    /**
     * @param  list<WordBox>  $boxes
     * @return array{tokens: list<string>}
     */
    public static function fromBoxes(array $boxes): array {
        $tokens = [];
        foreach ($boxes as $box) {
            if ($box->page !== 1 || ($box->y > 0.2 && $box->y < 0.8)) {
                continue;
            }
            $token = mb_strtolower(trim($box->text, " \t.,;:()[]|-/"));
            if (mb_strlen($token) < 3 || preg_match('/\d/', $token) === 1) {
                continue;
            }
            $tokens[$token] = true;
        }
        $list = array_keys($tokens);
        sort($list);

        return ['tokens' => $list];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function similarity(array $a, array $b): float {
        $x = array_fill_keys(array_map(strval(...), (array) ($a['tokens'] ?? [])), true);
        $y = array_fill_keys(array_map(strval(...), (array) ($b['tokens'] ?? [])), true);
        if ($x === [] || $y === []) {
            return 0.0;
        }
        $intersection = count(array_intersect_key($x, $y));

        return $intersection / count($x + $y);
    }
}
