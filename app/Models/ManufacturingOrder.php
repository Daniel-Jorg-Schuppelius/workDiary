<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Manufacturing\{ManufacturingOrderStatus, ProcurementMode};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Fertigungs-/Montageauftrag (Feature 047, MVP-062).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string|null $number
 * @property numeric-string $target_qty
 * @property ManufacturingOrderStatus $status
 * @property array<string, mixed>|null $bom_snapshot
 * @property int|null $customer_id
 * @property string|null $unit
 * @property-read Article $article
 * @property-read ArticleVariant|null $variant
 * @property-read Customer|null $customer
 */
class ManufacturingOrder extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'number',
        'article_id',
        'article_variant_id',
        'target_qty',
        'unit',
        'status',
        'priority',
        'planned_start',
        'due_at',
        'customer_id',
        'project_id',
        'responsible_user_id',
        'warehouse_id',
        'work_center_id',
        'planned_minutes',
        'procurement_mode',
        'subcontract_purchase_order_id',
        'procedure_template_version_id',
        'bom_snapshot',
        'variant_snapshot',
        'parameter_snapshot',
        'parameters',
        'procedure_run_id',
        'created_by',
        'released_at',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'target_qty' => 'decimal:4',
        'status' => ManufacturingOrderStatus::class,
        'procurement_mode' => ProcurementMode::class,
        'priority' => 'integer',
        'planned_minutes' => 'integer',
        'planned_start' => 'date',
        'due_at' => 'datetime',
        'released_at' => 'datetime',
        'completed_at' => 'datetime',
        'bom_snapshot' => 'array',
        'variant_snapshot' => 'array',
        'parameter_snapshot' => 'array',
        'parameters' => 'array',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function procedureVersion(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'procedure_template_version_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<ManufacturingOrderMaterial, $this> */
    public function materials(): HasMany {
        return $this->hasMany(ManufacturingOrderMaterial::class);
    }

    /** @return HasMany<ManufacturingOrderReport, $this> */
    public function reports(): HasMany {
        return $this->hasMany(ManufacturingOrderReport::class);
    }

    /** @return HasMany<StockDelivery, $this> */
    public function deliveries(): HasMany {
        return $this->hasMany(StockDelivery::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Kumulierte Gutmenge aus allen Rückmeldungen.
     *
     * @return numeric-string
     */
    public function goodTotal(): string {
        return $this->sumReports('good_qty');
    }

    /** Kumulierte Ausschussmenge. @return numeric-string */
    public function scrapTotal(): string {
        return $this->sumReports('scrap_qty');
    }

    /** Noch offene Gutmenge gegen die Sollmenge. @return numeric-string */
    public function openQuantity(): string {
        return bcsub($this->target_qty, $this->goodTotal(), 4);
    }

    /** @return numeric-string */
    private function sumReports(string $column): string {
        $sum = '0';
        foreach ($this->reports()->pluck($column) as $value) {
            $v = (string) $value;
            if (is_numeric($v)) {
                $sum = bcadd($sum, $v, 4);
            }
        }

        return $sum;
    }
}
