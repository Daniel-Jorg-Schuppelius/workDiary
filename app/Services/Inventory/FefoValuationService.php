<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FefoValuationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Models\{ArticleVariant, StockValuationLayer, Warehouse};
use Illuminate\Database\Eloquent\Builder;

/**
 * FEFO-Bewertung (First Expired, First Out) – Feature 047/048, E2/E3. Wie FIFO,
 * aber die Entnahme räumt zuerst die Schicht mit dem frühesten Verfallsdatum
 * (`best_before`); Schichten ohne Datum laufen ans Ende und folgen dann der
 * FIFO-Reihenfolge. Setzt Chargen-/MHD-Eingänge voraus
 * ({@see FifoValuationService::receiptIntoLot()}).
 */
class FefoValuationService extends FifoValuationService {
    public function method(): ValuationMethod {
        return ValuationMethod::Fefo;
    }

    /** @return Builder<StockValuationLayer> */
    protected function layerQuery(ArticleVariant $variant, Warehouse $warehouse): Builder {
        return StockValuationLayer::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('qty_remaining', '>', 0)
            ->orderByRaw('best_before is null') // Schichten ohne MHD ans Ende
            ->orderBy('best_before')
            ->orderBy('acquired_at')
            ->orderBy('id');
    }
}
