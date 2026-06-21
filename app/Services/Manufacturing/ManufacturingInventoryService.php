<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingInventoryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\{Article, ArticleVariant, ManufacturingOrder, ManufacturingOrderMaterial, Organization, Warehouse};
use App\Services\Inventory\{InventoryLedger, InventoryValuationManager, ReservationService, SerialService};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verbindet Fertigungsaufträge mit dem Lagerkern (Feature 047/048, MVP-071):
 * Materialbedarf gegen den Bestand reservieren, Ist-Verbrauch buchen und
 * Fertigerzeugnisse einlagern. Reservierung und tatsächlicher Verbrauch bleiben
 * getrennte Vorgänge; eine fehlende Deckung führt zu einer Teilreservierung
 * (Fehlmaterialprozess umgesetzt im {@see ShortageService}). Beim Verbrauch
 * werden zudem die Ist-Materialkosten aus dem Bewertungsverfahren erfasst.
 */
class ManufacturingInventoryService {
    public const SCALE = 4;

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly InventoryLedger $ledger,
        private readonly SerialService $serials,
        private readonly InventoryValuationManager $valuation,
    ) {}

    /**
     * Reserviert für jede Materialposition die noch offene Sollmenge gegen den
     * Bestand des Auftrags-Lagers (höchstens die verfügbare Menge).
     */
    public function reserveMaterials(ManufacturingOrder $order): ManufacturingOrder {
        $warehouse = $order->warehouse;
        if ($warehouse === null) {
            throw new RuntimeException('Fertigungsauftrag ohne Lagerort kann kein Material reservieren.');
        }

        DB::transaction(function () use ($order, $warehouse): void {
            foreach ($order->materials()->where('is_tool', false)->get() as $material) {
                $open = bcsub($material->target_qty, $material->reserved_qty, self::SCALE);
                if (bccomp($open, '0', self::SCALE) <= 0) {
                    continue;
                }

                $variant = $this->resolveVariant($material->article_id, $material->article_variant_id);
                if ($variant === null) {
                    continue; // nicht bestandsführend (z. B. Leistung) → keine Reservierung
                }

                $reservation = $this->reservations->reserveUpToAvailable($variant, $warehouse, $open, source: $order);
                if ($reservation === null) {
                    continue;
                }

                $material->reserved_qty = bcadd($material->reserved_qty, $reservation->openQuantity(), self::SCALE);
                $material->stock_reservation_id = $reservation->id;
                $material->save();
            }
        });

        return $order;
    }

    /** Bucht den tatsächlichen Verbrauch einer Materialposition (über die Reservierung). */
    public function consume(ManufacturingOrderMaterial $material, string $qty): ManufacturingOrderMaterial {
        $qty = $this->positive($qty);
        if (bccomp($qty, '0', self::SCALE) <= 0) {
            return $material;
        }

        return DB::transaction(function () use ($material, $qty): ManufacturingOrderMaterial {
            $variant = $this->resolveVariant($material->article_id, $material->article_variant_id);
            $warehouse = $material->order?->warehouse;

            // Ist-Stückkosten VOR der Entnahme aus dem aktiven Bewertungsverfahren
            // erfassen (Grundlage echter Nachkalkulation), ohne den Buchungspfad zu
            // ändern.
            $organization = Organization::query()->find($material->order?->organization_id);
            if ($variant instanceof ArticleVariant && $warehouse instanceof Warehouse && $organization instanceof Organization) {
                $unit = $this->valuation->forVariant($variant, $organization)->unitCost($variant, $warehouse);
                $material->actual_cost = bcadd((string) $material->actual_cost, bcmul($qty, $unit, self::SCALE), self::SCALE);
            }

            $reservation = $material->reservation;
            if ($reservation !== null) {
                $this->reservations->fulfill($reservation, $qty);
            } elseif ($variant instanceof ArticleVariant && $warehouse instanceof Warehouse) {
                $this->ledger->issue($variant, $warehouse, $qty, allowNegative: true);
            }

            $material->consumed_qty = bcadd($material->consumed_qty, $qty, self::SCALE);
            $material->save();

            return $material;
        });
    }

    /** Lagert eine Gutmenge des Fertigerzeugnisses in das Auftrags-Lager ein. */
    public function receiveFinishedGood(ManufacturingOrder $order, string $qty): void {
        $warehouse = $order->warehouse;
        if ($warehouse === null) {
            throw new RuntimeException('Fertigungsauftrag ohne Lagerort kann nichts einlagern.');
        }

        $variant = $this->resolveVariant($order->article_id, $order->article_variant_id);
        if ($variant === null) {
            throw new RuntimeException('Fertigerzeugnis ohne bestandsführende Variante.');
        }

        // Mengenbuchung und Seriennummern-Generierung gehören atomar zusammen:
        // schlägt die Seriengenerierung fehl, darf die Gutmenge nicht eingebucht
        // bleiben (sonst Bestand ohne zugehörige Seriennummern).
        DB::transaction(function () use ($order, $variant, $warehouse, $qty): void {
            $this->ledger->finishedGoodReceipt($variant, $warehouse, $this->positive($qty));

            // Eigenfertigung: für seriennummernpflichtige Erzeugnisse je Stück eine
            // Seriennummer erzeugen (E2). Greift nur bei ganzzahligen Stückmengen.
            $article = $order->article;
            if ($article instanceof Article && $article->serial_required) {
                $count = (int) $this->positive($qty);
                if ($count > 0) {
                    $this->serials->generate($variant, $count, $warehouse, $order, $order->created_by);
                }
            }
        });
    }

    /** Löst die bestandsführende Variante auf: konkrete Variante oder Standard-/erste Variante des Artikels. */
    private function resolveVariant(int $articleId, ?int $variantId): ?ArticleVariant {
        if ($variantId !== null) {
            return ArticleVariant::query()->find($variantId);
        }

        return ArticleVariant::query()
            ->where('article_id', $articleId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : $value;
    }
}
