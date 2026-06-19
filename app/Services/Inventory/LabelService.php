<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabelService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\{Article, ArticleVariant, StockLot, StockSerial};

/**
 * Baut die Etikettendaten für Variante/Charge/Seriennummer (Feature 048, E5).
 * Liefert Code + Codetyp + Beschriftung; das eigentliche Rendern (Barcode/QR,
 * Layout) übernimmt das PDF-Toolkit über das Vorlagensystem (Feature 032) als
 * Folgeausbau.
 *
 * @phpstan-type LabelData array{code: string, code_type: string, title: string, subtitle: ?string, lines: list<string>}
 */
class LabelService {
    /** @return LabelData */
    public function forVariant(ArticleVariant $variant): array {
        $article = $variant->article;
        $gtin = $variant->gtin;
        $sku = $variant->sku;
        $code = $gtin ?? $sku ?? (string) $variant->id;
        $codeType = $gtin !== null ? 'gtin' : ($sku !== null ? 'sku' : 'internal');

        return [
            'code' => $code,
            'code_type' => $codeType,
            'title' => $article instanceof Article ? $article->name : (string) $variant->id,
            'subtitle' => $variant->name ?? $sku,
            'lines' => array_values(array_filter([$sku, $gtin])),
        ];
    }

    /** @return LabelData */
    public function forSerial(StockSerial $serial): array {
        $article = $serial->article;

        return [
            'code' => $serial->serial_no,
            'code_type' => 'serial',
            'title' => $article instanceof Article ? $article->name : (string) $serial->article_id,
            'subtitle' => $serial->serial_no,
            'lines' => [$serial->serial_no, $serial->status->label()],
        ];
    }

    /** @return LabelData */
    public function forLot(StockLot $lot): array {
        $variant = $lot->variant;
        $article = $variant instanceof ArticleVariant ? $variant->article : null;
        $bestBefore = $lot->best_before?->format('Y-m-d');

        return [
            'code' => $lot->lot_no,
            'code_type' => 'lot',
            'title' => $article instanceof Article ? $article->name : (string) $lot->article_variant_id,
            'subtitle' => $lot->lot_no,
            'lines' => array_values(array_filter([$lot->lot_no, $bestBefore])),
        ];
    }
}
