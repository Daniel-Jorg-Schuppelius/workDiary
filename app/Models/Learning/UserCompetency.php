<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserCompetency.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Erreichte Kompetenzstufe einer Person (Feature 149, MVP-745).
 *
 * `source` hält fest, woher die Einschätzung stammt — eine Einschätzung
 * ohne Herkunft wäre nicht überprüfbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $competency_id
 * @property int $level
 * @property string $source
 * @property int|null $learning_enrollment_id
 * @property int|null $assessed_by_user_id
 * @property Carbon $assessed_on
 * @property Carbon|null $valid_until
 * @property string|null $note
 * @property-read Competency|null $competency
 */
class UserCompetency extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'competency_id',
        'level',
        'source',
        'learning_enrollment_id',
        'assessed_by_user_id',
        'assessed_on',
        'valid_until',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'level' => 'integer',
        'assessed_on' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
    ];

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo {
        return $this->belongsTo(Competency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function isExpired(?Carbon $on = null): bool {
        $on ??= Carbon::today();

        return $this->valid_until !== null && $this->valid_until->lessThan($on);
    }
}
