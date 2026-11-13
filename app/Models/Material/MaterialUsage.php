<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Material;

use App\Casts\{MoneyCast, PercentageCast, QuantityCast};
use App\Enums\Material\MaterialStockStatus;
use App\Models\Asset\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Inventory\Warehouse;
use App\Models\Time\Timesheet;
use App\Services\Billing\DocumentTotalsCalculator;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $timesheet_id
 * @property int|null $material_id
 * @property int|null $asset_id
 * @property string $description
 * @property \CommonToolkit\ValueObjects\Quantity|null $quantity
 * @property string $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property \CommonToolkit\ValueObjects\Money|null $line_total_net
 * @property bool $billed
 * @property MaterialStockStatus|null $stock_status Bestandswirkung (MVP-1679); leer = nicht lagergeführt
 * @property int|null $stock_warehouse_id Lager, aus dem gebucht wurde bzw. wird
 * @property-read \App\Models\Material\Material|null $material
 * @property-read Warehouse|null $stockWarehouse
 */
class MaterialUsage extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'timesheet_id',
        'material_id',
        'asset_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'line_total_net',
        'billed',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => QuantityCast::class . ':unit,3',
        'unit_price' => MoneyCast::class . ':currency,4',
        'tax_rate' => PercentageCast::class . ':2',
        'line_total_net' => MoneyCast::class . ':currency,2',
        'billed' => 'boolean',
        'stock_status' => MaterialStockStatus::class,
    ];

    /** Zeilen- und Zettelsumme sind decimal(12,2) (UI-Fuzz 2026-09-21: 1264). */
    public const float MAX_TIMESHEET_SUM = 9999999999.99;

    /** Sprengt Menge × Preis zusammen mit den übrigen Zeilen des Zettels die speicherbare Summe? */
    public static function exceedsTimesheetSum(Timesheet $timesheet, mixed $quantity, mixed $unitPrice, ?int $exceptId = null): bool {
        $others = $timesheet->materialUsages()
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('line_total_net');

        $line = DocumentTotalsCalculator::lineNet(
            is_numeric($quantity) ? (string) $quantity : '0',
            $unitPrice instanceof Money || is_numeric($unitPrice) ? $unitPrice : null,
        )->withScale(2);

        return (float) $line->getAmount() + (float) $others > self::MAX_TIMESHEET_SUM;
    }

    protected static function booted(): void {
        static::saving(function (MaterialUsage $usage): void {
            $qty = ($usage->quantity?->getValue()->toFloat() ?? 0.0);
            $price = $usage->unit_price ?? Money::zero(CurrencyCode::Euro);
            $usage->line_total_net = DocumentTotalsCalculator::lineNet($qty, $price)->withScale(2);
        });
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo {
        return $this->belongsTo(Timesheet::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Warehouse, $this> Lager der Bestandsbuchung (MVP-1679). */
    public function stockWarehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class, 'stock_warehouse_id');
    }

    /** Bestandswirkung für Zeile und Meldung, etwa „Vom Bestand abgebucht · Hauptlager“; null ohne Lagerbezug. */
    public function stockNotice(): ?string {
        if ($this->stock_status === null) {
            return null;
        }
        $warehouse = $this->stockWarehouse?->name;

        return $warehouse !== null
            ? (string) __('material.stock.notice', ['status' => $this->stock_status->label(), 'warehouse' => $warehouse])
            : $this->stock_status->label();
    }
}
