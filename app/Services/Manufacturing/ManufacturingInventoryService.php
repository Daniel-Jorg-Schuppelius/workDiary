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

use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\{ArticleVariant, ManufacturingOrder, ManufacturingOrderMaterial, Organization, Warehouse};
use App\Services\Inventory\{InventoryLedger, InventoryValuationManager, ReservationService, SerialService, StockPosting};
use App\Support\DecimalQty;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
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
        private readonly ShortageService $shortages,
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

                $reserved = $reservation?->openQuantity() ?? '0';
                if ($reservation !== null) {
                    $material->reserved_qty = bcadd($material->reserved_qty, $reserved, self::SCALE);
                    $material->stock_reservation_id = $reservation->id;
                    $material->save();
                }

                // Vollaudit 2026-07 (M20): Fehlmengen nicht still übergehen —
                // Unterdeckung erzeugt einen Beschaffungsbedarf (048: „aus
                // Fehlmengen einen Beschaffungsbedarf oder offenen Punkt").
                $shortage = bcsub($open, $reserved, self::SCALE);
                if (bccomp($shortage, '0', self::SCALE) > 0) {
                    $this->recordShortage($order, $material, $variant, $warehouse, $shortage);
                }
            }
        });

        return $order;
    }

    /**
     * Beschaffungsbedarf aus einer Reservierungs-Fehlmenge (Vollaudit 2026-07,
     * M20) — idempotent je Auftrag+Variante (kein Doppel-Bedarf bei erneutem
     * Reservierungslauf).
     */
    private function recordShortage(ManufacturingOrder $order, ManufacturingOrderMaterial $material, ArticleVariant $variant, Warehouse $warehouse, string $shortage): void {
        $article = $material->article;
        if ($article === null) {
            return;
        }

        $exists = \App\Models\ProcurementRequest::query()
            ->where('organization_id', $order->organization_id)
            ->where('article_variant_id', $variant->id)
            ->where('source_type', $order->getMorphClass())
            ->where('source_id', $order->getKey())
            ->where('status', \App\Enums\Manufacturing\ProcurementStatus::Open->value)
            ->exists();
        if ($exists) {
            return;
        }

        $this->shortages->createProcurementRequest(
            $article,
            $variant,
            $shortage,
            $warehouse,
            $order,
            note: (string) __('Fehlmenge bei Materialreservierung (Auftrag :number).', ['number' => (string) $order->number]),
        );
    }

    /**
     * Bucht den tatsächlichen Verbrauch einer Materialposition (über die
     * Reservierung). Ohne Reservierung ist die Negativ-Entnahme seit Vollaudit
     * 2026-07 (M20) NICHT mehr still — sie erfordert die explizite Freigabe.
     */
    public function consume(ManufacturingOrderMaterial $material, string $qty, bool $allowNegative = false): ManufacturingOrderMaterial {
        $qty = DecimalQty::positive($qty);
        if (bccomp($qty, '0', self::SCALE) <= 0) {
            return $material;
        }

        return DB::transaction(function () use ($material, $qty, $allowNegative): ManufacturingOrderMaterial {
            $variant = $this->resolveVariant($material->article_id, $material->article_variant_id);
            $warehouse = $material->order?->warehouse;

            // Ist-Stückkosten VOR der Entnahme aus dem aktiven Bewertungsverfahren
            // erfassen (Grundlage echter Nachkalkulation), ohne den Buchungspfad zu
            // ändern.
            $organization = Organization::query()->find($material->order?->organization_id);
            if ($variant instanceof ArticleVariant && $warehouse instanceof Warehouse && $organization instanceof Organization) {
                $unit = $this->valuation->forVariant($variant, $organization)->unitCost($variant, $warehouse);
                $material->actual_cost = Money::of(bcadd($material->actual_cost?->getAmount() ?? '0', bcmul($qty, $unit, self::SCALE), self::SCALE), CurrencyCode::Euro, 4);
            }

            $reservation = $material->reservation;
            if ($reservation !== null) {
                $this->reservations->fulfill($reservation, $qty);
            } elseif ($variant instanceof ArticleVariant && $warehouse instanceof Warehouse) {
                // Vollaudit 2026-07 (M20): keine stille Negativ-Entnahme mehr —
                // allowNegative kommt jetzt als explizite Freigabe vom Aufrufer.
                $this->ledger->issue($variant, $warehouse, $qty, allowNegative: $allowNegative);
            }

            $material->consumed_qty = bcadd($material->consumed_qty, $qty, self::SCALE);
            $material->save();

            return $material;
        });
    }

    /**
     * Gibt nach Abschluss/Stornierung verbliebene (nicht verbrauchte)
     * Materialreservierungen wieder frei, sodass der gesperrte Bestand zurück in
     * die Verfügbarkeit fällt (MVP-071, „Restreservierung frei").
     *
     * @return numeric-string  die insgesamt freigegebene Menge
     */
    public function releaseRemainingReservations(ManufacturingOrder $order): string {
        return DB::transaction(function () use ($order): string {
            $released = '0';
            foreach ($order->materials()->whereNotNull('stock_reservation_id')->get() as $material) {
                $reservation = $material->reservation;
                if ($reservation === null) {
                    continue;
                }

                $open = $reservation->openQuantity();
                if (bccomp($open, '0', self::SCALE) <= 0) {
                    continue;
                }

                $this->reservations->release($reservation);
                $released = bcadd($released, $open, self::SCALE);
            }

            return $released;
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
            $this->ledger->finishedGoodReceipt($variant, $warehouse, DecimalQty::positive($qty));

            // Eigenfertigung: für seriennummernpflichtige Erzeugnisse je Stück eine
            // Seriennummer erzeugen (E2). Greift nur bei ganzzahligen Stückmengen.
            $article = $order->article;
            if ($article->serial_required) {
                $count = (int) DecimalQty::positive($qty);
                if ($count > 0) {
                    $this->serials->generate($variant, $count, $warehouse, $order, $order->created_by);
                }
            }
        });
    }

    /**
     * Verbucht gemeldeten Ausschuss des Fertigerzeugnisses als eigene
     * Journalbewegung im Zustand `scrap` (MVP-071): je Variante/Lager
     * nachvollziehbar, ohne physischen oder verfügbaren Bestand zu verändern
     * (Ausschuss ist kein verwendbarer Bestand). Ohne Lager oder
     * bestandsführende Variante bleibt die Menge nur in der Rückmeldung
     * dokumentiert.
     */
    public function recordScrap(ManufacturingOrder $order, string $qty, ?int $reportId = null): void {
        $qty = DecimalQty::positive($qty);
        if (bccomp($qty, '0', self::SCALE) <= 0) {
            return;
        }

        $warehouse = $order->warehouse;
        $variant = $this->resolveVariant($order->article_id, $order->article_variant_id);
        if ($warehouse === null || $variant === null) {
            return;
        }

        $this->ledger->post(new StockPosting(
            $variant,
            $warehouse,
            StockState::Scrap,
            $qty,
            StockMovementType::Scrap,
            idempotencyKey: $reportId !== null ? 'mfg-scrap:' . $reportId : null,
            source: $order,
        ));
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
}
