<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubcontractService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\ProcurementMode;
use App\Models\{ManufacturingOrder, ManufacturingOrderMaterial, Organization, PurchaseOrder, Supplier, Warehouse};
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fremdfertigung / Lohnauftrag (Feature 047/048, E7): vergibt einen
 * Fertigungsauftrag an einen Lieferanten und legt dafür einen Lieferantenauftrag
 * (E4) über das Erzeugnis an. Das Beistellmaterial ergibt sich aus den
 * Materialpositionen des Auftrags.
 */
class SubcontractService {
    public function __construct(private readonly PurchaseOrderService $orders) {}

    public function commission(ManufacturingOrder $order, Supplier $supplier, ?int $createdBy = null): PurchaseOrder {
        $warehouse = $order->warehouse;
        $article = $order->article;
        $organization = Organization::query()->find($order->organization_id);
        if (! $warehouse instanceof Warehouse) {
            throw new RuntimeException('Fertigungsauftrag ohne Lagerort.');
        }
        // $article ist über die nicht-nullbare FK article_id garantiert vorhanden.
        if (! $organization instanceof Organization) {
            throw new RuntimeException('Fertigungsauftrag ohne Organisation.');
        }

        return DB::transaction(function () use ($order, $supplier, $warehouse, $article, $organization, $createdBy): PurchaseOrder {
            $po = $this->orders->createDraft($organization, $supplier, $warehouse, [
                'created_by' => $createdBy,
                'note' => trim('Fremdfertigung ' . ($order->number ?? '')),
            ]);
            $this->orders->addLine($po, $article, $order->target_qty, ['unit' => $order->unit]);

            $order->forceFill([
                'procurement_mode' => ProcurementMode::Subcontract->value,
                'subcontract_purchase_order_id' => $po->id,
            ])->save();

            return $po;
        });
    }

    /**
     * Beistellmaterial: die Materialpositionen, die dem Lohnfertiger gestellt
     * werden (ohne Werkzeuge).
     *
     * @return Collection<int, ManufacturingOrderMaterial>
     */
    public function providedMaterials(ManufacturingOrder $order): Collection {
        return $order->materials()->where('is_tool', false)->get();
    }
}
