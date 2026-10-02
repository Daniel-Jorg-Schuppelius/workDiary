<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentOutline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\DocumentLineKind;
use App\Models\Contracts\DocumentLine;
use CommonToolkit\ValueObjects\Money;

/**
 * Gliederung eines Belegs für die Anzeige (MVP-1054): Ordnungszahlen aus den
 * Titeln (`2.3` = dritte Position unter Titel 2, ohne Titel fortlaufend) und
 * eine Zwischensumme am Ende jedes Titels. Rechnet keine Belegsumme — die
 * bleibt beim {@see DocumentTotalsCalculator}.
 */
final class DocumentOutline {
    /**
     * @template T of DocumentLine
     *
     * @param  iterable<T>  $lines  in Positionsreihenfolge
     * @param  (callable(T): bool)|null  $counts  welche bepreisten Zeilen in die Titelsumme gehen (Standard: Positionen)
     * @return list<array{type: 'line', line: T, number: string|null}|array{type: 'subtotal', title: T, number: string, amount: Money}>
     */
    public static function rows(iterable $lines, ?callable $counts = null): array {
        $counts ??= static fn (DocumentLine $line): bool => $line->lineKind() === DocumentLineKind::Item;

        /** @var list<array{type: 'line', line: T, number: string|null}|array{type: 'subtotal', title: T, number: string, amount: Money}> $rows */
        $rows = [];
        $titleNo = 0;
        $itemNo = 0;
        /** @var T|null $title */
        $title = null;
        $titleNumber = '';
        $sum = null;

        foreach ($lines as $line) {
            $kind = $line->lineKind();
            if ($kind === DocumentLineKind::Title) {
                if (($row = self::subtotal($title, $titleNumber, $sum)) !== null) {
                    $rows[] = $row;
                }
                $titleNumber = (string) ++$titleNo;
                $title = $line;
                $itemNo = 0;
                $sum = null;
                $rows[] = ['type' => 'line', 'line' => $line, 'number' => $titleNumber];

                continue;
            }
            if (! $kind->isPriced()) {
                $rows[] = ['type' => 'line', 'line' => $line, 'number' => null];

                continue;
            }
            $itemNo++;
            $rows[] = ['type' => 'line', 'line' => $line, 'number' => $title === null ? (string) $itemNo : $titleNumber . '.' . $itemNo];
            if ($title !== null && $counts($line)) {
                $net = $line->netAmount();
                $sum = $sum === null ? $net : $sum->plus($net->withScale($sum->getScale()));
            }
        }
        if (($row = self::subtotal($title, $titleNumber, $sum)) !== null) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @template T of DocumentLine
     *
     * @param  T|null  $title
     * @return array{type: 'subtotal', title: T, number: string, amount: Money}|null
     */
    private static function subtotal(?DocumentLine $title, string $number, ?Money $sum): ?array {
        return $title !== null && $sum !== null ? ['type' => 'subtotal', 'title' => $title, 'number' => $number, 'amount' => $sum] : null;
    }
}
