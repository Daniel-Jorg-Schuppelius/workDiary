<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StrategicObjective.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Strategisches Ziel mit Kennzahlen (MVP-942); Investitionen lassen sich
 * einem Ziel zuordnen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property int|null $owner_user_id
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property bool $is_active
 * @property int|null $created_by
 */
class StrategicObjective extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'title', 'description', 'owner_user_id', 'valid_from', 'valid_until', 'is_active', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean'];

    /** @return HasMany<StrategicKeyResult, $this> */
    public function keyResults(): HasMany {
        return $this->hasMany(StrategicKeyResult::class)->orderBy('position');
    }

    /** @return HasMany<InvestmentCase, $this> */
    public function cases(): HasMany {
        return $this->hasMany(InvestmentCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
