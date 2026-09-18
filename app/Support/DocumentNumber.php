<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentNumber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Support\Facades\App;

/**
 * Zahlenformat der Belegsprache (Feature 034, MVP-726; Vollscan H19).
 *
 * Ergänzt {@see DocumentLocale}: Die Sprache eines Belegs war seit MVP-721
 * einstellbar, die Zahlen darin blieben aber deutsch — eine französische
 * Rechnung wies `1.234,56 €` aus, wo der Empfänger `1 234,56 €` erwartet.
 * Für einen Beleg, den ein Kunde prüft und bezahlt, ist das kein Schönheits-
 * fehler: `1.234` liest sich außerhalb des deutschen Sprachraums als
 * Tausendstel-Bruch.
 *
 * **Gilt ausschließlich für Belege**, nicht für die Oberfläche. Interne
 * Listen bleiben in der Sprache des Betreibers — wer die Anwendung bedient,
 * soll nicht wechselnde Zahlenformate lesen müssen, weil ein Kunde
 * italienisch abgerechnet wird.
 *
 * Die Formatierung selbst kommt aus dem common-toolkit ({@see Money::format()},
 * {@see Decimal::format()}); hier steht nur, welche Konvention zu welcher
 * Sprache gehört.
 */
final class DocumentNumber {
    /**
     * Trennzeichen und Symbolstellung je Sprache.
     *
     * Für Französisch bewusst das geschützte Leerzeichen U+00A0 und nicht das
     * typografisch korrekte schmale U+202F: Letzteres fehlt in etlichen
     * PDF-Schriften und erschiene im Beleg als leeres Kästchen.
     *
     * @var array<string, array{decimal: string, thousands: string, symbol_before: bool}>
     */
    private const CONVENTIONS = [
        'de' => ['decimal' => ',', 'thousands' => '.', 'symbol_before' => false],
        'en' => ['decimal' => '.', 'thousands' => ',', 'symbol_before' => true],
        'fr' => ['decimal' => ',', 'thousands' => "\u{00A0}", 'symbol_before' => false],
        'it' => ['decimal' => ',', 'thousands' => '.', 'symbol_before' => false],
        'es' => ['decimal' => ',', 'thousands' => '.', 'symbol_before' => false],
    ];

    /** @return array{decimal: string, thousands: string, symbol_before: bool} */
    public static function conventions(?string $locale = null): array {
        $locale = $locale ?? App::getLocale();
        $base = strtolower(substr($locale, 0, 2));

        return self::CONVENTIONS[$base] ?? self::CONVENTIONS['de'];
    }

    /**
     * Geldbetrag in der Belegsprache.
     *
     * Erwartet den Decimal-String der Anwendung (`Money::getAmount()`), damit
     * unterwegs nicht gerundet wird.
     */
    public static function money(string|float|int|null $amount, CurrencyCode $currency = CurrencyCode::Euro, ?string $locale = null): string {
        $c = self::conventions($locale);
        $money = is_float($amount) ? Money::ofFloat($amount, $currency, 2) : Money::of($amount ?? 0, $currency, 2);

        return $money->format(true, true, $c['decimal'], $c['thousands'], $c['symbol_before']);
    }

    /**
     * Reine Zahl (Menge, Satz) in der Belegsprache — ohne Währung.
     *
     * {@see Decimal::format()} rundet präzise (HalfUp) und kommt mit dem
     * Mehrbyte-Tausendertrenner (U+00A0) zurecht.
     */
    public static function decimal(string|float|int|null $value, int $decimals = 2, bool $withThousandsSeparator = true, ?string $locale = null): string {
        $c = self::conventions($locale);
        $number = is_float($value) ? Decimal::ofFloat($value, $decimals) : Decimal::of($value ?? 0, $decimals);

        return $number->format($c['decimal'], $withThousandsSeparator ? $c['thousands'] : '');
    }
}
