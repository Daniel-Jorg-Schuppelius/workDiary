<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Finance\BillingMode;
use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\{Article, ArticleVariant, Customer, ManufacturingOrder, Organization, StockDelivery, StockSerial, Warehouse};
use App\Services\Finance\BillingModeResolver;
use App\Services\Inventory\{ExternalStockMirror, InventoryLedger, InventoryValuationManager, SerialService};
use App\Support\DecimalQty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auslieferung von Fertigerzeugnissen (Feature 047, MVP-074): bucht den Bestand
 * der konkreten Variante ab und legt die Faktura-Übergabe an. Das führende
 * Fakturasystem ergibt sich aus der Datenführerschaft des Kunden
 * ({@see BillingModeResolver}). Lager- und Faktura-Status bleiben getrennt: das
 * Markieren eines Faktura-Fehlers verändert die erfolgte Lagerbuchung nicht.
 */
class DeliveryService {
    public const SCALE = 4;

    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly BillingModeResolver $billingMode = new BillingModeResolver(),
        private readonly ?SerialService $serials = null,
        private readonly ?ExternalStockMirror $mirror = null,
        private readonly ?InventoryValuationManager $valuation = null,
    ) {}

    /**
     * @param list<int>|null $serialIds konkrete Seriennummern, die mit dieser
     *                                  Auslieferung an den Kunden gehen (E2)
     */
    public function deliver(
        ArticleVariant $variant,
        Warehouse $warehouse,
        string $qty,
        ?ManufacturingOrder $order = null,
        ?Customer $customer = null,
        bool $allowNegative = false,
        ?int $createdBy = null,
        ?array $serialIds = null,
    ): StockDelivery {
        $qty = DecimalQty::positive($qty);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $order, $customer, $allowNegative, $createdBy, $serialIds): StockDelivery {
            // Lagerbuchung zuerst — schlägt sie fehl (Unterdeckung), entsteht keine Auslieferung. Abgang über das
            // aktive Bewertungsverfahren (Durchschnitt/FIFO/FEFO), damit der COGS-Kostensnapshot an der Bewegung steht.
            $organization = Organization::query()->find($variant->organization_id);
            $issueMovement = $organization instanceof Organization
                ? ($this->valuation ?? app(InventoryValuationManager::class))->forVariant($variant, $organization)
                    ->issue($variant, $warehouse, $qty, $allowNegative, $createdBy)
                : $this->ledger->issue($variant, $warehouse, $qty, allowNegative: $allowNegative);

            // Standard-Buchungspfad → externe Outbox spiegeln, falls die Org extern führt.
            if ($organization instanceof Organization) {
                ($this->mirror ?? app(ExternalStockMirror::class))->mirror($issueMovement, $organization);
            }

            $target = $customer !== null
                ? $this->targetFor($this->billingMode->effectiveFor($customer))
                : 'workdiary';

            $article = $variant->article;
            $baseUnit = $article instanceof Article ? $article->base_unit : 'Stk';
            $name = $variant->name ?? ($article instanceof Article ? $article->name : (string) $variant->id);

            $delivery = StockDelivery::query()->create([
                'organization_id' => $variant->organization_id,
                'manufacturing_order_id' => $order?->id,
                'article_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $customer?->id,
                'quantity' => $qty,
                'unit' => $baseUnit,
                'sku_snapshot' => $variant->sku,
                'name_snapshot' => $name,
                'unit_price_snapshot' => $variant->effectiveSalePrice(),
                'currency' => $variant->currency ?? 'EUR',
                'stock_status' => 'delivered',
                'facturation_status' => DeliveryFacturationStatus::Pending->value,
                'facturation_target' => $target,
                'delivered_at' => Carbon::now(),
                'created_by' => $createdBy,
            ]);

            if ($serialIds !== null && $serialIds !== []) {
                $serialService = $this->serials ?? app(SerialService::class);
                $serials = StockSerial::query()
                    ->where('article_variant_id', $variant->id)
                    ->whereIn('id', $serialIds)
                    ->get();
                foreach ($serials as $serial) {
                    $serialService->ship($serial, $delivery, $customer);
                }
            }

            return $delivery;
        });
    }

    /** Setzt das Faktura-Ergebnis – unabhängig vom (bereits erfolgten) Lagerstatus. */
    public function markFacturationResult(StockDelivery $delivery, DeliveryFacturationStatus $status, ?string $externalId = null): StockDelivery {
        $delivery->facturation_status = $status;
        if ($externalId !== null) {
            $delivery->external_id = $externalId;
        }
        $delivery->save();

        return $delivery;
    }

    private function targetFor(BillingMode $mode): string {
        return match ($mode) {
            BillingMode::Lexoffice => 'lexoffice',
            BillingMode::Datev => 'datev',
            BillingMode::OrgaMax => 'orgamax',
            BillingMode::SevDesk => 'sevdesk',
            BillingMode::Easybill => 'easybill',
            BillingMode::Workdiary => 'workdiary',
        };
    }
}
