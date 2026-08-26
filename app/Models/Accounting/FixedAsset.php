<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAsset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Enums\Finance\{DepreciationMethod, FixedAssetStatus};
use App\Models\{Asset, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Anlage im Anlagenregister (Feature 133, MVP-698) — die buchhalterische
 * Sicht: AK/HK, Nutzungsdauer, Restwert, Konten. Das Gerät ({@see Asset})
 * ist optional verknüpft; die AfA-Zeilen berechnet der
 * {@see \App\Services\Accounting\DepreciationCalculator}, gebucht wird nur
 * über die Buchungs-Inbox.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_no
 * @property string $name
 * @property int|null $asset_id
 * @property Carbon $acquired_on
 * @property CurrencyCode $currency
 * @property Money|null $acquisition_cost
 * @property Money|null $residual_value
 * @property int $useful_life_months
 * @property DepreciationMethod $depreciation_method
 * @property int|null $asset_account_id
 * @property int|null $depreciation_account_id
 * @property FixedAssetStatus $status
 * @property Carbon|null $disposed_on
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property int|null $created_by_user_id
 */
class FixedAsset extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;
    use SoftDeletes;

    /**
     * Geschäftsjahr, für das ein Buchungsvorschlag gebildet wird — kein
     * Spaltenwert, sondern Kontext des DepreciationAdapter (eine Anlage
     * liefert je Jahr einen eigenen Vorschlag mit eigenem Idempotenzschlüssel).
     */
    public ?AccountingFiscalYear $depreciationYear = null;

    protected $fillable = [
        'organization_id',
        'asset_no',
        'name',
        'asset_id',
        'acquired_on',
        'currency',
        'acquisition_cost',
        'residual_value',
        'useful_life_months',
        'depreciation_method',
        'asset_account_id',
        'depreciation_account_id',
        'status',
        'disposed_on',
        'source_type',
        'source_id',
        'note',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'asset_no' => 'integer',
        'acquired_on' => 'date',
        'disposed_on' => 'date',
        'currency' => CurrencyCode::class,
        'acquisition_cost' => MoneyCast::class . ':currency,2',
        'residual_value' => MoneyCast::class . ':currency,2',
        'useful_life_months' => 'integer',
        'depreciation_method' => DepreciationMethod::class,
        'status' => FixedAssetStatus::class,
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function assetAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'asset_account_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function depreciationAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'depreciation_account_id');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<FixedAsset>  $query
     * @return Builder<FixedAsset>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('status', FixedAssetStatus::Active->value);
    }

    /** Anzeige-Kennung im Register (z. B. „AN-3"). */
    public function displayNo(): string {
        return 'AN-' . $this->asset_no;
    }

    /** AfA-Bemessungsgrundlage: AK/HK abzüglich Restwert (nie negativ). */
    public function depreciableBase(): Money {
        $cost = $this->acquisition_cost ?? Money::zero($this->currency);
        $residual = $this->residual_value ?? Money::zero($this->currency);
        $base = $cost->minus($residual);

        return $base->isNegative() ? Money::zero($this->currency) : $base;
    }

    public function isDisposed(): bool {
        return $this->status === FixedAssetStatus::Disposed;
    }

    /** Kopie mit Jahreskontext für den Adapter — das Original bleibt unberührt. */
    public function forFiscalYear(AccountingFiscalYear $year): static {
        $copy = clone $this;
        $copy->depreciationYear = $year;

        return $copy;
    }

    public function acquiredOn(): CarbonImmutable {
        return CarbonImmutable::parse($this->acquired_on)->startOfDay();
    }

    public function disposedOn(): ?CarbonImmutable {
        return $this->disposed_on === null ? null : CarbonImmutable::parse($this->disposed_on)->startOfDay();
    }
}
