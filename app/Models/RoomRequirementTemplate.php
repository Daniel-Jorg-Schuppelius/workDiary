<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomRequirementTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Facility\RoomRequirementKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Vorlage einer raumbezogenen fachlichen Anforderung je Gewerk
 * (Feature 042 / 027).
 *
 * Wird über Branchenprofile vorbelegt (org-weit, ohne dass beim Import bereits
 * Räume existieren müssen) und kann beim Pflegen eines Raums als
 * {@see RoomRequirement} übernommen werden.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property RoomRequirementKind $kind
 * @property string $label
 * @property string|null $level
 * @property string|null $note
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RoomRequirementTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'kind',
        'label',
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
}
