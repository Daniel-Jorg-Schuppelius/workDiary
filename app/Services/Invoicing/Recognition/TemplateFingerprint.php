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

    /** Höchstens so viele Layout-Varianten merkt sich eine Vorlage zusätzlich zum ersten Fingerabdruck. */
    public const MAX_VARIANTS = 5;

    /** Ab dieser Ähnlichkeit gilt ein Beleg als dieselbe Variante — eine neue wird nicht gemerkt. */
    public const VARIANT_THRESHOLD = 0.8;

    /**
     * Ähnlichkeit eines Belegs (`$a`, nur `tokens`) zu einer Vorlage (`$b`, `tokens` und seit Phase 155
     * optional `variants`): die beste über alle gemerkten Varianten.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function similarity(array $a, array $b): float {
        $best = self::jaccard((array) ($a['tokens'] ?? []), (array) ($b['tokens'] ?? []));
        foreach ((array) ($b['variants'] ?? []) as $variant) {
            if (is_array($variant)) {
                $best = max($best, self::jaccard((array) ($a['tokens'] ?? []), $variant));
            }
        }

        return $best;
    }

    /**
     * Fingerabdruck einer Vorlage um die Kopfform eines weiteren Belegs ergänzen (Phase 155): nur wenn der Beleg
     * keiner gemerkten Variante nahekommt; die älteste Variante weicht, wenn es zu viele werden. So trifft eine
     * Vorlage auch Belege desselben Lieferanten mit anderem Adressblock oder Ansprechpartner.
     *
     * @param  array<string, mixed>  $fingerprint
     * @param  array<string, mixed>  $document  Fingerabdruck des Belegs ({@see fromBoxes()})
     * @return array<string, mixed>
     */
    public static function withVariant(array $fingerprint, array $document): array {
        $tokens = array_values(array_map(strval(...), (array) ($document['tokens'] ?? [])));
        if ($tokens === []) {
            return $fingerprint;
        }
        if (($fingerprint['tokens'] ?? []) === []) {
            return ['tokens' => $tokens] + $fingerprint;
        }
        if (self::similarity($document, $fingerprint) >= self::VARIANT_THRESHOLD) {
            return $fingerprint;
        }
        $variants = array_values(array_filter((array) ($fingerprint['variants'] ?? []), 'is_array'));
        $variants[] = $tokens;
        if (count($variants) > self::MAX_VARIANTS) {
            $variants = array_slice($variants, -self::MAX_VARIANTS);
        }

        return ['variants' => $variants] + $fingerprint;
    }

    /**
     * @param  array<mixed>  $a
     * @param  array<mixed>  $b
     */
    private static function jaccard(array $a, array $b): float {
        $x = array_fill_keys(array_map(strval(...), $a), true);
        $y = array_fill_keys(array_map(strval(...), $b), true);
        if ($x === [] || $y === []) {
            return 0.0;
        }

        return count(array_intersect_key($x, $y)) / count($x + $y);
    }
}
