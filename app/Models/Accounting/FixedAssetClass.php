<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetClass.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\DepreciationMethod;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anlagenklasse (Feature 133, MVP-999): Vorgaben für neue Anlagen — Nutzungsdauer,
 * Methode und Konten. Bestehende Anlagen behalten ihre Werte, wenn die Klasse
 * sich ändert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $useful_life_months
 * @property DepreciationMethod $depreciation_method
 * @property int|null $asset_account_id
 * @property int|null $depreciation_account_id
 * @property bool $is_active
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FixedAssetClass extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'useful_life_months',
        'depreciation_method',
        'asset_account_id',
        'depreciation_account_id',
        'is_active',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'useful_life_months' => 'integer',
        'depreciation_method' => DepreciationMethod::class,
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<AccountingAccount, $this> */
    public function assetAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'asset_account_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function depreciationAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'depreciation_account_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
