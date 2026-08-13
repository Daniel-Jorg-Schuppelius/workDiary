<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftRotationEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slot eines Rollplans (MVP-522): Woche × ISO-Wochentag → Schichttyp.
 *
 * @property int $id
 * @property int $shift_rotation_id
 * @property int $week_index
 * @property int $iso_weekday
 * @property int $shift_type_id
 */
class ShiftRotationEntry extends Model {
    public $timestamps = false;

    protected $fillable = [
        'shift_rotation_id',
        'week_index',
        'iso_weekday',
        'shift_type_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'week_index' => 'integer',
        'iso_weekday' => 'integer',
    ];

    /** @return BelongsTo<ShiftRotation, $this> */
    public function rotation(): BelongsTo {
        return $this->belongsTo(ShiftRotation::class, 'shift_rotation_id');
    }

    /** @return BelongsTo<ShiftType, $this> */
    public function shiftType(): BelongsTo {
        return $this->belongsTo(ShiftType::class);
    }
}
