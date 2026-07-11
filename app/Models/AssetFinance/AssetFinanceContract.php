<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceContract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Enums\AssetFinance\{AssetFinanceKind, AssetFinanceStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\{CostCenter, Project, PurchaseOrder, Supplier, User};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Leasing-/Finanzierungsakte (Feature 074, MVP-271). Entscheidung D11:
 * eigenes, leasingspezifisches Vertragsmodell — kein generisches CLM.
 * Konditionen/Raten/Restwerte sind vertrauliche Finanzdaten
 * (assetFinance.finance); Ist-Werte werden nur referenziert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property AssetFinanceKind $kind
 * @property AssetFinanceStatus $status
 * @property string $partner_name
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon|null $ends_on
 * @property numeric-string|null $rate_amount
 * @property CurrencyCode $currency
 * @property array<string, mixed>|null $terms_snapshot
 */
class AssetFinanceContract extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'number', 'kind', 'status', 'partner_name',
        'supplier_id', 'contract_no', 'starts_on', 'ends_on',
        'notice_period_days', 'payment_rhythm', 'rate_amount', 'currency',
        'special_payment', 'residual_value', 'purchase_option_amount',
        'terms_snapshot', 'cost_center_id', 'cost_center_label', 'project_id',
        'purchase_order_id', 'responsible_user_id', 'insurance_note', 'notes',
        'created_by', 'closed_at', 'closed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => AssetFinanceKind::class,
        'status' => AssetFinanceStatus::class,
        'currency' => CurrencyCode::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'notice_period_days' => 'integer',
        'rate_amount' => 'decimal:2',
        'special_payment' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'purchase_option_amount' => 'decimal:2',
        'terms_snapshot' => 'array',
        'closed_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [
            AssetFinanceStatus::Draft->value,
            AssetFinanceStatus::Active->value,
            AssetFinanceStatus::Ending->value,
            AssetFinanceStatus::Extended->value,
        ]);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return HasMany<AssetFinanceContractAsset, $this> */
    public function contractAssets(): HasMany {
        return $this->hasMany(AssetFinanceContractAsset::class);
    }

    /** @return HasMany<AssetFinanceTerm, $this> */
    public function terms(): HasMany {
        return $this->hasMany(AssetFinanceTerm::class);
    }

    /** @return HasMany<AssetFinanceRateSchedule, $this> */
    public function rateSchedules(): HasMany {
        return $this->hasMany(AssetFinanceRateSchedule::class)->orderBy('due_on');
    }

    /** @return HasMany<AssetFinanceDeadline, $this> */
    public function deadlines(): HasMany {
        return $this->hasMany(AssetFinanceDeadline::class)->orderBy('due_on');
    }

    /** @return HasMany<AssetFinanceUsageLimit, $this> */
    public function usageLimits(): HasMany {
        return $this->hasMany(AssetFinanceUsageLimit::class);
    }

    /** @return HasMany<AssetFinanceOption, $this> */
    public function options(): HasMany {
        return $this->hasMany(AssetFinanceOption::class);
    }

    /** @return HasMany<AssetFinanceEndProcess, $this> */
    public function endProcesses(): HasMany {
        return $this->hasMany(AssetFinanceEndProcess::class);
    }

    /** @return HasMany<AssetFinanceCostSnapshot, $this> */
    public function costSnapshots(): HasMany {
        return $this->hasMany(AssetFinanceCostSnapshot::class)->latest();
    }
}
