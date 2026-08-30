<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAccessToken.php
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
 * Einmal-Zugang für Lernende ohne Benutzerkonto (Feature 149, MVP-742).
 *
 * Der Klartext-Token wird nie gespeichert — nur sein Hash. Das Modell
 * versteckt ihn zusätzlich vor Serialisierung und Audit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $first_used_at
 * @property Carbon|null $last_used_at
 * @property int $use_count
 * @property Carbon|null $revoked_at
 * @property-read LearningEnrollment|null $enrollment
 */
class LearningAccessToken extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** Den Hash nie serialisieren oder auditieren. */
    protected $hidden = [
        'token_hash',
    ];

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'token_hash',
        'expires_at',
        'first_used_at',
        'last_used_at',
        'use_count',
        'revoked_at',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'first_used_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'use_count' => 'integer',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isUsable(?Carbon $now = null): bool {
        $now ??= Carbon::now();

        return $this->revoked_at === null && $this->expires_at->greaterThan($now);
    }
}
