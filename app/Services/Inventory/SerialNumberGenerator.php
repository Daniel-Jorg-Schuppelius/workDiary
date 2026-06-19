<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialNumberGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Numbering\NumberScope;
use App\Models\{Article, ArticleVariant, Organization, StockSerial};
use App\Services\Numbering\NumberSequenceService;

/**
 * Erzeugt Seriennummern für die Eigenfertigung über die lokale Nummernhoheit
 * ({@see NumberScope::Serial}). Optional wird eine Luhn-Prüfziffer angehängt, die
 * Tipp-/Scanfehler erkennt (scannbare, tippsichere Nummern). Artikel können über
 * `articles.serial_scheme` (prefix/padding/check_digit) ein eigenes Schema mit
 * artikelbezogenem Zähler vorgeben.
 */
class SerialNumberGenerator {
    public function __construct(private readonly NumberSequenceService $numbers) {}

    public function generate(Organization|int $organization): string {
        $base = $this->numbers->next($organization, NumberScope::Serial);

        if ((bool) config('numbering.defaults.serial.check_digit', false)) {
            return $base . '-' . $this->luhnCheckDigit($base);
        }

        return $base;
    }

    /**
     * Erzeugt eine Seriennummer für eine Variante: artikelbezogenes Schema, falls
     * hinterlegt, sonst der Org-Nummernkreis. Der Zähler des Artikelschemas ergibt
     * sich aus den bereits vergebenen Nummern (nie recycelt); die Dublettensperre
     * fängt seltene Kollisionen ab.
     */
    public function generateFor(ArticleVariant $variant): string {
        $article = $variant->article;
        $scheme = $article instanceof Article && is_array($article->serial_scheme) ? $article->serial_scheme : null;

        if ($scheme === null || ! isset($scheme['prefix']) || (string) $scheme['prefix'] === '') {
            return $this->generate((int) $variant->organization_id);
        }

        $prefix = (string) $scheme['prefix'];
        $padding = isset($scheme['padding']) ? (int) $scheme['padding'] : 5;
        $seq = StockSerial::query()
            ->where('organization_id', $variant->organization_id)
            ->where('article_id', $variant->article_id)
            ->count() + 1;

        $number = $prefix . '-' . str_pad((string) $seq, max(1, $padding), '0', STR_PAD_LEFT);

        $checkDigit = $scheme['check_digit'] ?? config('numbering.defaults.serial.check_digit', false);
        if ((bool) $checkDigit) {
            $number .= '-' . $this->luhnCheckDigit($number);
        }

        return $number;
    }

    /** Luhn-Prüfziffer über alle Ziffern der Eingabe (0–9). */
    public function luhnCheckDigit(string $value): int {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        $sum = 0;
        $double = true;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = ! $double;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
