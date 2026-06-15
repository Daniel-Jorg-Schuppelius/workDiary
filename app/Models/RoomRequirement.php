<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Facility\RoomRequirementKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\RoomRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Raumbezogene fachliche Anforderung je Gewerk (Feature 027).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $room_id
 * @property RoomRequirementKind $kind
 * @property string|null $level
 * @property string|null $note
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RoomRequirement extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<RoomRequirementFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'room_id',
        'kind',
        'level',
        'note',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'kind' => RoomRequirementKind::class,
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo {
        return $this->belongsTo(Room::class);
    }
}
