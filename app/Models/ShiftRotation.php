<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftRotation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rollplan (MVP-522): mehrwöchiger Dienstrhythmus, der sich automatisch
 * fortschreibt. Wochen × Wochentage referenzieren Schichttypen; Tage ohne
 * Eintrag sind dienstfrei.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $weeks_count
 * @property bool $is_active
 */
class ShiftRotation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'weeks_count',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'weeks_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<ShiftRotationEntry, $this> */
    public function entries(): HasMany {
        return $this->hasMany(ShiftRotationEntry::class);
    }

    /** @return HasMany<ShiftRotationAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(ShiftRotationAssignment::class);
    }
}
