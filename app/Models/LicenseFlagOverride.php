<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseFlagOverride.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override-Eintrag für lizenzierte Feature-Flags (MVP-047 Option A).
 *
 * Existiert ein Eintrag, gilt der Flag als lokal deaktiviert
 * (für die Organisation oder — falls `organization_id` NULL ist —
 * plattformweit). Es ist nicht möglich, nicht-lizenzierte Flags
 * per Override zu aktivieren.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $flag
 * @property string|null $reason
 * @property \Carbon\CarbonImmutable $disabled_at
 * @property int|null $disabled_by_user_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class LicenseFlagOverride extends Model {
    protected $fillable = [
        'organization_id',
        'flag',
        'reason',
        'disabled_at',
        'disabled_by_user_id',
    ];

    protected $casts = [
        'disabled_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function disabledBy(): BelongsTo {
        return $this->belongsTo(User::class, 'disabled_by_user_id');
    }
}
