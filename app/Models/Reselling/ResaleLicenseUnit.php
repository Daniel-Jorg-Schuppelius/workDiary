<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLicenseUnit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\LicenseUnitStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Einzellizenz eines Pakets (MVP-1024): eine verkaufbare Einheit mit ihrem
 * Schlüsselsatz. Der Status wird aus Zuordnung, Sperre und Vollständigkeit
 * abgeleitet ({@see LicenseUnitStatus::derive()}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $batch_id
 * @property int $position
 * @property CarbonImmutable|null $blocked_at
 * @property string|null $blocked_reason
 * @property int|null $blocked_by_user_id
 * @property int|null $keys_count
 * @property-read ResaleLicenseBatch $batch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResaleLicenseKey> $keys
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResaleLicenseAssignment> $assignments
 * @property-read ResaleLicenseAssignment|null $activeAssignment
 */
class ResaleLicenseUnit extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'batch_id',
        'position',
        'blocked_at',
        'blocked_reason',
        'blocked_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'blocked_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<ResaleLicenseBatch, $this> */
    public function batch(): BelongsTo {
        return $this->belongsTo(ResaleLicenseBatch::class, 'batch_id');
    }

    /** @return HasMany<ResaleLicenseKey, $this> */
    public function keys(): HasMany {
        return $this->hasMany(ResaleLicenseKey::class, 'unit_id');
    }

    /** @return HasMany<ResaleLicenseAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(ResaleLicenseAssignment::class, 'unit_id');
    }

    /** @return HasOne<ResaleLicenseAssignment, $this> */
    public function activeAssignment(): HasOne {
        return $this->hasOne(ResaleLicenseAssignment::class, 'active_unit_id');
    }

    /**
     * Alles, was {@see status()} braucht, in einer Abfrage.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStockState(Builder $query): Builder {
        return $query->withCount('keys')->with(['batch', 'activeAssignment.customer', 'activeAssignment.foreignCustomer']);
    }

    /**
     * Verkaufbar: nicht verkauft, nicht gesperrt, Schlüsselsatz vollständig.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder {
        return $query->whereNull('blocked_at')
            ->whereDoesntHave('activeAssignment')
            ->whereRaw('(select count(*) from resale_license_keys k where k.unit_id = resale_license_units.id) >= (select b.key_count from resale_license_batches b where b.id = resale_license_units.batch_id)');
    }

    /**
     * Unvollständig: weder verkauft noch gesperrt, mindestens ein Schlüssel fehlt.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeIncomplete(Builder $query): Builder {
        return $query->whereNull('blocked_at')
            ->whereDoesntHave('activeAssignment')
            ->whereRaw('(select count(*) from resale_license_keys k where k.unit_id = resale_license_units.id) < (select b.key_count from resale_license_batches b where b.id = resale_license_units.batch_id)');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, LicenseUnitStatus $status): Builder {
        return match ($status) {
            LicenseUnitStatus::Available => $query->available(),
            LicenseUnitStatus::Incomplete => $query->incomplete(),
            LicenseUnitStatus::Sold => $query->whereHas('activeAssignment'),
            LicenseUnitStatus::Blocked => $query->whereNotNull('blocked_at')->whereDoesntHave('activeAssignment'),
        };
    }

    public function status(): LicenseUnitStatus {
        $sold = $this->relationLoaded('activeAssignment') ? $this->activeAssignment !== null : $this->activeAssignment()->exists();
        $keys = $this->keys_count ?? $this->keys()->count();

        return LicenseUnitStatus::derive($sold, $this->blocked_at !== null, $keys >= $this->batch->key_count);
    }

    public function label(): string {
        return $this->batch->reference . ' · #' . $this->position;
    }
}
