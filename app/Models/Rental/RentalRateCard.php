<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateCard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalRateCardStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Versionierte Verleih-Preisliste (D10). Eine neue Version löst die alte ab;
 * abgelöste Versionen bleiben lesbar, weil Verleihakten sie referenzieren.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $version
 * @property RentalRateCardStatus $status
 */
class RentalRateCard extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'name', 'version', 'status', 'valid_from',
        'valid_to', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RentalRateCardStatus::class,
        'version' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->where('status', RentalRateCardStatus::Active->value);
    }

    /** @return HasMany<RentalRateItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(RentalRateItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Eingefrorene Konditionen für den Akten-Snapshot (D10).
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array {
        return [
            'rate_card_id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'items' => $this->items->map(fn (RentalRateItem $item): array => [
                'kind' => $item->kind->value,
                'label' => $item->label,
                'group_code' => $item->group_code,
                'amount' => (string) $item->amount,
                'unit' => $item->unit,
                'min_duration_days' => $item->min_duration_days,
            ])->values()->all(),
        ];
    }
}
