<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PickListLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\{ArticleVariant, StockLot, Warehouse, WarehouseBin};

/**
 * Eine Position der Kommissionierliste (Feature 048, MVP-706): Variante,
 * Entnahmeort (Lager, optional Platz und Charge), zu entnehmende Menge in
 * der Basiseinheit und der am Ort verfügbare Bestand.
 */
final class PickListLine {
    public function __construct(
        public readonly ArticleVariant $variant,
        public readonly Warehouse $warehouse,
        public readonly ?WarehouseBin $bin,
        public readonly ?StockLot $lot,
        /** @var numeric-string */
        public readonly string $qty,
        public readonly string $unit,
        /** @var numeric-string */
        public readonly string $available,
    ) {}

    public function sku(): string {
        return (string) ($this->variant->sku ?? '');
    }

    /** Artikelname + Variantenbezeichnung (wie in der Bestandsübersicht). */
    public function label(): string {
        $article = $this->variant->article->name ?? '';
        $variant = $this->variant->name ?? $this->variant->option_signature ?? '';

        return trim($article . ($variant !== '' ? ' — ' . $variant : ''));
    }

    /** Verfügbarer Bestand deckt die Position nicht (Fehlmenge im Druck hervorheben). */
    public function isShort(): bool {
        return bccomp($this->available, $this->qty, InventoryLedger::SCALE) < 0;
    }
}
