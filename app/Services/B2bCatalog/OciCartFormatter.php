<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OciCartFormatter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\B2bCatalog;

use App\Models\B2b\B2bCatalogItem;
use ERechnungToolkit\Enums\UnitCode;

/**
 * Baut die OCI-4.0-`NEW_ITEM-*`-Felder der Warenkorb-Rückgabe (Feature 099,
 * MVP-457). Die workDiary-Artikelnummer geht als MATNR **und** VENDORMAT mit —
 * sie ist die Rückreferenz, über die der openTRANS-Auftragseingang (MVP-458)
 * die Positionen wieder zuordnet. Preise mit Punkt-Dezimal, PRICEUNIT 1,
 * EAN/GTIN als EXT_PRODUCT_ID sofern vorhanden. Artikel mit Kupferdaten
 * erhalten den Tagespreis-Kupferzuschlag als eigene Warenkorbzeile
 * (Feature 107, MVP-603).
 */
class OciCartFormatter {
    public function __construct(private readonly \App\Services\Procurement\MetalSurchargeService $metals) {}

    /**
     * @param  array<int, array{item: B2bCatalogItem, quantity: float}>  $lines
     * @return array<string, string>  Formularfelder (Name => Wert)
     */
    public function fields(array $lines): array {
        $fields = [];
        $n = 0;

        foreach ($lines as $line) {
            $item = $line['item'];
            $article = $item->article;
            if ($article === null) {
                continue;
            }

            $n++;
            $price = $item->effectivePrice();

            $fields["NEW_ITEM-DESCRIPTION[{$n}]"] = (string) $article->name;
            $fields["NEW_ITEM-MATNR[{$n}]"] = (string) $article->number;
            $fields["NEW_ITEM-VENDORMAT[{$n}]"] = (string) $article->number;
            $fields["NEW_ITEM-QUANTITY[{$n}]"] = $this->decimal($line['quantity']);
            $fields["NEW_ITEM-UNIT[{$n}]"] = $this->isoUnit((string) $article->base_unit);
            $fields["NEW_ITEM-PRICE[{$n}]"] = $price !== null ? $price->getAmount() : '0.0000';
            $fields["NEW_ITEM-CURRENCY[{$n}]"] = $article->currency->value;
            $fields["NEW_ITEM-PRICEUNIT[{$n}]"] = '1';

            if (is_string($article->description) && trim($article->description) !== '') {
                $fields["NEW_ITEM-LONGTEXT_{$n}:132[]"] = trim($article->description);
            }
            if (is_string($article->gtin) && $article->gtin !== '') {
                $fields["NEW_ITEM-EXT_PRODUCT_ID[{$n}]"] = $article->gtin;
            }

            // MVP-603: Tagespreis-Kupferzuschlag als eigene Position — die
            // Suffix-Nummer matcht beim openTRANS-Rücklauf bewusst keinen
            // Artikel und bleibt dort Textposition.
            $surcharge = $this->metals->salesSurcharge($article);
            if ($surcharge !== null) {
                $n++;
                $fields["NEW_ITEM-DESCRIPTION[{$n}]"] = (string) __('b2b_catalog.copper_surcharge_position', ['number' => (string) $article->number]);
                $fields["NEW_ITEM-MATNR[{$n}]"] = $article->number . '-CU';
                $fields["NEW_ITEM-VENDORMAT[{$n}]"] = $article->number . '-CU';
                $fields["NEW_ITEM-QUANTITY[{$n}]"] = $this->decimal($line['quantity']);
                $fields["NEW_ITEM-UNIT[{$n}]"] = $this->isoUnit((string) $article->base_unit);
                $fields["NEW_ITEM-PRICE[{$n}]"] = $surcharge->getAmount();
                $fields["NEW_ITEM-CURRENCY[{$n}]"] = $article->currency->value;
                $fields["NEW_ITEM-PRICEUNIT[{$n}]"] = '1';
            }
        }

        return $fields;
    }

    private function decimal(float $value): string {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    /**
     * `base_unit` ist Freitext (Default „Stk"); Einkaufssysteme erwarten
     * UN/ECE-Rec-20-Codes. Auflösung über {@see UnitCode::fromText()}
     * (Feature 107, W7); nicht auflösbare Einheiten werden wie bisher roh
     * durchgereicht — OCI-Gegenstellen kennen teils eigene Kürzel.
     */
    private function isoUnit(string $baseUnit): string {
        $unit = trim($baseUnit);
        if ($unit === '') {
            return UnitCode::PIECE->value;
        }

        return UnitCode::fromText($unit)->value ?? $unit;
    }
}
