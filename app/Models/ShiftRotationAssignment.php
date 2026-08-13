<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftRotationAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rollplan-Zuweisung (MVP-522): verankert einen Rhythmus je Mitarbeiter an
 * einem Referenz-Montag (`anchor_date` = Montag der Woche 0).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $shift_rotation_id
 * @property int $user_id
 * @property Carbon $anchor_date
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 */
class ShiftRotationAssignment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'shift_rotation_id',
        'user_id',
        'anchor_date',
        'valid_from',
        'valid_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'anchor_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<ShiftRotation, $this> */
    public function rotation(): BelongsTo {
        return $this->belongsTo(ShiftRotation::class, 'shift_rotation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
