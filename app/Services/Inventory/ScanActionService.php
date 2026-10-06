<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanActionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, ScanAction, StockMovementType, StockState};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse};
use App\Support\DecimalQty;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mobile Bestandsbuchung per Scan (Feature 048, E5): löst den Code zur Variante
 * auf und bucht die gewählte Aktion (Eingang/Entnahme/Umlagerung) gegen das
 * append-only Journal. Entnahme und Umlagerung prüfen die Verfügbarkeit; die
 * Umlagerung nimmt ihre Chargen mit (E4).
 */
class ScanActionService {
    public const SCALE = 4;

    public function __construct(
        private readonly BarcodeResolver $resolver,
        private readonly InventoryLedger $ledger,
        private readonly LotStockReader $lotStock,
    ) {}

    /**
     * Der Eingang liefert die gebuchte Bewegung, Entnahme und Umlagerung ihren
     * Abgang (je Charge eine Bewegung). Ein gescannter Chargencode bucht genau
     * diese Charge.
     *
     * @param array{actor?: int|null, target?: Warehouse|null} $options
     */
    public function book(string $code, ScanAction $action, Warehouse $warehouse, string $qty, array $options = []): StockMovement|StockIssue {
        $match = $this->resolver->resolve($code);
        $variant = $match->variant;
        if (! $variant instanceof ArticleVariant) {
            throw new RuntimeException('Unbekannter oder nicht bestandsführender Code: ' . trim($code));
        }

        $qty = DecimalQty::positive($qty);
        $actor = $options['actor'] ?? null;

        // Vollaudit 2026-07 (M19, E2): chargen-/serienpflichtige Artikel nicht
        // still als anonymer Bestand ein-/ausbuchen — Erfassung läuft über den
        // Wareneingang (Bestellung) bzw. die Chargen-/Serienverwaltung.
        // Umlagerung bleibt zulässig (Bestand wechselt nur den Ort).
        $article = $variant->article;
        if ($action !== ScanAction::Transfer
            && (($article->batch_required ?? false) || ($article->serial_required ?? false))) {
            throw new RuntimeException((string) __('inventory.error.tracked_article_manual_move'));
        }

        return match ($action) {
            ScanAction::Receipt => $this->ledger->receipt($variant, $warehouse, $qty, actorUserId: $actor),
            ScanAction::Issue => $this->ledger->issue($variant, $warehouse, $qty, actorUserId: $actor, lot: $match->lot),
            ScanAction::Transfer => $this->transfer($variant, $warehouse, $options['target'] ?? null, $qty, $actor, $match->lot),
        };
    }

    /**
     * Umlagerung je Charge: die Menge wird wie ein Abgang zugeteilt (gescannte
     * Charge, sonst FEFO, Rest ohne Charge) und je Teil mit derselben Charge
     * im Ziel-Lager zugebucht — dort bleibt sie pickbar.
     *
     * @param numeric-string $qty
     */
    private function transfer(ArticleVariant $variant, Warehouse $from, ?Warehouse $to, string $qty, ?int $actor, ?StockLot $scanned): StockIssue {
        if (! $to instanceof Warehouse) {
            throw new RuntimeException('Umlagerung ohne Ziel-Lager.');
        }

        return DB::transaction(function () use ($variant, $from, $to, $qty, $actor, $scanned): StockIssue {
            // Prüfung und Zuteilung in derselben Transaktion: beide sperren den Bestand gegen parallele Abgänge.
            if (Decimal::of($this->ledger->availableForUpdate($variant, $from), self::SCALE)->lessThan(Decimal::of($qty, self::SCALE))) {
                throw new RuntimeException('Umlagerung übersteigt den verfügbaren Bestand.');
            }

            $leaving = [];
            foreach ($this->lotStock->allocate($variant, $from, $qty, explicit: $scanned, ownership: OwnershipType::Own)->parts as ['lot' => $lot, 'qty' => $partQty]) {
                $leaving[] = $this->ledger->post(new StockPosting(
                    $variant, $from, StockState::Physical, DecimalQty::negative($partQty), StockMovementType::TransferOut,
                    OwnershipType::Own, actorUserId: $actor, stockLotId: $lot?->id,
                ))->setRelation('lot', $lot);
                $this->ledger->post(new StockPosting(
                    $variant, $to, StockState::Physical, $partQty, StockMovementType::TransferIn,
                    OwnershipType::Own, actorUserId: $actor, stockLotId: $lot?->id,
                ));
            }

            return new StockIssue($leaving);
        });
    }
}
