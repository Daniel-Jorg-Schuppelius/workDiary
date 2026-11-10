<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\Demo;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Enums\Inventory\WarehouseKind;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{InventoryOutboxEntry, StockLevelSetting, StockMovement, StockReservation, StockValuation, StockValuationLayer, Warehouse, WarehouseBin};
use App\Models\Platform\Organization;
use App\Services\Article\{ArticleService, VariantResolver};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Inventory\{InventoryLedger, StockLevelService};

/**
 * Lager zur Vorführung (MVP-1532): Hauptlager und Fahrzeuglager mit dem
 * Material der Musterbranche als Lagerartikel, Zugängen, Verbrauch und
 * Meldebeständen — ein Artikel liegt unter dem Meldebestand, damit der
 * Bestellvorschlag etwas zeigt.
 */
final class InventoryDemoBlock implements DemoBlock {
    /** Zugang Hauptlager, Verbrauch Hauptlager, Zugang Fahrzeug, Mindestbestand, Meldebestand je Material. */
    private const array QUANTITIES = [
        ['24', '6', '4', '5', '8'],
        ['200', '60', '50', '50', '80'],
        ['12', '9', '2', '4', '6'],
    ];

    public function __construct(
        private readonly ArticleService $articles,
        private readonly VariantResolver $variants,
        private readonly InventoryLedger $ledger,
        private readonly StockLevelService $levels,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        if (! $context->moduleActive('module.lager') || $admin === null) {
            return ['warehouses' => 0, 'stock_movements' => 0];
        }
        $organization = $context->organization;

        $main = Warehouse::query()->create([
            'organization_id' => $organization->id,
            'code' => 'HL',
            'name' => (string) __('demo.inventory.warehouse_main'),
            'kind' => WarehouseKind::Fixed,
            'is_default' => true,
            'active' => true,
            'location_note' => (string) __('demo.inventory.warehouse_main_note'),
            'created_by' => $admin->id,
        ]);
        $vehicle = Warehouse::query()->create([
            'organization_id' => $organization->id,
            'code' => 'FZ1',
            'name' => (string) __('demo.inventory.warehouse_vehicle'),
            'kind' => WarehouseKind::Vehicle,
            'is_default' => false,
            'active' => true,
            'created_by' => $admin->id,
        ]);

        /** @var list<array{sku: string, name: string, unit: string, price: string}> $materials */
        $materials = $context->blueprint['materials'];
        $movements = 0;
        foreach ($materials as $index => $material) {
            [$receipt, $issue, $vehicleReceipt, $minStock, $reorderPoint] = self::QUANTITIES[$index % count(self::QUANTITIES)];
            $article = $this->articles->createArticle($organization, [
                'number' => $material['sku'],
                'name' => $material['name'],
                'type' => ArticleType::Consumable,
                'base_unit' => $material['unit'],
                'status' => ArticleStatus::Active,
                'stockable' => true,
                'purchasable' => true,
                'sellable' => true,
                'default_sale_price' => $material['price'],
                'currency' => 'EUR',
                'created_by' => $admin->id,
            ]);
            $variant = $this->variants->createVariant($article, [], [
                'sku' => $material['sku'],
                'is_default' => true,
                'sale_price' => $material['price'],
                'currency' => 'EUR',
                'created_by' => $admin->id,
            ]);

            $key = 'demo:' . $material['sku'];
            $this->ledger->receipt($variant, $main, $receipt, idempotencyKey: $key . ':receipt', actorUserId: $admin->id);
            $this->ledger->issue($variant, $main, $issue, idempotencyKey: $key . ':issue', actorUserId: $admin->id);
            $this->ledger->receipt($variant, $vehicle, $vehicleReceipt, idempotencyKey: $key . ':vehicle', actorUserId: $admin->id);
            $movements += 3;
            $this->levels->setLevels($variant, $main, $minStock, $reorderPoint);
        }

        return ['warehouses' => 2, 'stock_movements' => $movements];
    }

    public function purge(Organization $organization): void {
        $variantIds = ArticleVariant::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('id');
        StockLevelSetting::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        StockReservation::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        InventoryOutboxEntry::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        StockValuationLayer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        StockValuation::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        // Demo-Mandant (is_demo): Demo-Bestandsbuchungen sind keine Nachweise; GoBD-Ausnahme im Gate begründet.
        StockMovement::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        WarehouseBin::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        Warehouse::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        ArticleVariant::query()->withoutGlobalScopes()->whereKey($variantIds)->delete();
        Article::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
