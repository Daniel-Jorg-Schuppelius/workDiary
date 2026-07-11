<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReturnReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\{RentalCondition, RentalReturnFollowUp};
use App\Models\{Asset, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

/**
 * Rücknahmeprotokoll (MVP-265): Zustand, Schäden, Fehlteile, Reinigung,
 * Verbrauch und Folgeentscheidung (Sperre/Reparatur/Reklamation) —
 * getrennt vom Übergabeprotokoll.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $rental_case_id
 * @property int $asset_id
 * @property \Illuminate\Support\Carbon $reported_at
 * @property RentalCondition $condition
 * @property RentalReturnFollowUp $follow_up
 * @property bool $cleaning_required
 */
class RentalReturnReport extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'rental_case_id', 'asset_id', 'reported_at',
        'reported_by', 'condition', 'checklist', 'meter_value',
        'operating_hours', 'fuel_level', 'damages', 'missing_parts',
        'cleaning_required', 'consumables', 'follow_up', 'follow_up_note',
        'signature_name', 'signed_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'condition' => RentalCondition::class,
        'follow_up' => RentalReturnFollowUp::class,
        'reported_at' => 'datetime',
        'checklist' => 'array',
        'consumables' => 'array',
        'meter_value' => 'decimal:4',
        'operating_hours' => 'decimal:2',
        'cleaning_required' => 'boolean',
        'signed_at' => 'datetime',
    ];

    /** @return BelongsTo<RentalCase, $this> */
    public function rentalCase(): BelongsTo {
        return $this->belongsTo(RentalCase::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return MorphMany<RentalConditionItem, $this> */
    public function conditionItems(): MorphMany {
        return $this->morphMany(RentalConditionItem::class, 'report');
    }

    /** @return MorphMany<RentalAccessoryItem, $this> */
    public function accessoryItems(): MorphMany {
        return $this->morphMany(RentalAccessoryItem::class, 'report');
    }
}
