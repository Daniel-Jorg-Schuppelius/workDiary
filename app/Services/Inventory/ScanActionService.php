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

use App\Enums\Inventory\{OwnershipType, ScanAction, StockMovementType};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse};
use App\Models\Platform\Organization;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mobile Bestandsbuchung per Scan (Feature 048, E5): löst den Code zur Variante
 * auf und bucht die gewählte Aktion (Eingang/Entnahme/Umlagerung) gegen das
 * append-only Journal. Entnahme und Umlagerung prüfen die Verfügbarkeit; die
 * Umlagerung nimmt ihre Chargen mit (E4).
 */
class ScanActionService {
    public function __construct(
        private readonly BarcodeResolver $resolver,
        private readonly InventoryLedger $ledger,
        private readonly InventoryValuationManager $valuation,
        private readonly ManualStockReceipt $receipts,
    ) {}

    /**
     * Der Eingang liefert die gebuchte Bewegung, Entnahme und Umlagerung ihren
     * Abgang (je Charge eine Bewegung). Ein gescannter Chargencode bucht genau
     * diese Charge.
     *
     * @param array{actor?: int|null, target?: Warehouse|null, unit?: string|null, unit_cost?: string|null} $options `unit`: Einheit des Artikels, leer = Basiseinheit; `unit_cost`: Einstandspreis je erfasster Einheit des Eingangs
     */
    public function book(string $code, ScanAction $action, Warehouse $warehouse, string $qty, array $options = []): StockMovement|StockIssue {
        $match = $this->resolver->resolve($code);
        $variant = $match->variant;
        if (! $variant instanceof ArticleVariant) {
            throw new RuntimeException('Unbekannter oder nicht bestandsführender Code: ' . trim($code));
        }

        // MVP-1584: Eingabe in einer Einheit des Artikels, gebucht wird in Basiseinheit.
        $entered = EnteredQuantity::of($variant, $qty, $options['unit'] ?? null);
        $qty = $entered->base;
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

        // MHD-Pflicht (MVP-1579): der Scan-Eingang kennt weder Charge noch Datum.
        if ($action === ScanAction::Receipt && ($article->shelf_life_required ?? false)) {
            throw new RuntimeException((string) __('inventory.error.shelf_life_manual_receipt'));
        }

        $organization = $warehouse->organization;

        return match ($action) {
            // Bewertet wie der Eingang im Lager (MVP-1709); der Scan bucht nur Eigenbestand.
            ScanAction::Receipt => $this->receipts->book($variant, $warehouse, $entered, OwnershipType::Own, $options['unit_cost'] ?? null, actorUserId: $actor),
            // Bewertet wie die Entnahme im Lager (MVP-1692).
            ScanAction::Issue => $organization instanceof Organization
                ? $this->valuation->forVariant($variant, $organization)->issue($variant, $warehouse, $qty, actorUserId: $actor, lot: $match->lot, entered: $entered)
                : $this->ledger->issue($variant, $warehouse, $qty, actorUserId: $actor, lot: $match->lot, entered: $entered),
            ScanAction::Transfer => $this->transfer($variant, $warehouse, $options['target'] ?? null, $entered, $actor, $match->lot),
        };
    }

    /**
     * Umlagerung je Charge: die Menge wird wie ein Abgang zugeteilt (gescannte
     * Charge, sonst FEFO, Rest ohne Charge) und je Teil mit derselben Charge
     * im Ziel-Lager zugebucht — dort bleibt sie pickbar. Das Bewertungsbuch
     * wandert zum selben Wert mit (MVP-1726); ohne Organisation gibt es keins.
     */
    private function transfer(ArticleVariant $variant, Warehouse $from, ?Warehouse $to, EnteredQuantity $entered, ?int $actor, ?StockLot $scanned): StockIssue {
        if (! $to instanceof Warehouse) {
            throw new RuntimeException('Umlagerung ohne Ziel-Lager.');
        }

        $organization = $from->organization;
        if ($organization instanceof Organization) {
            return $this->valuation->forVariant($variant, $organization)->transfer($variant, $from, $to, $entered->base, $actor, $scanned, $entered);
        }

        return DB::transaction(function () use ($variant, $from, $to, $entered, $actor, $scanned): StockIssue {
            $leaving = $this->ledger->issue($variant, $from, $entered->base, actorUserId: $actor, lot: $scanned, entered: $entered, type: StockMovementType::TransferOut);
            foreach ($leaving as $out) {
                $this->ledger->transferIn($variant, $out, $to, $entered);
            }

            return $leaving;
        });
    }
}
