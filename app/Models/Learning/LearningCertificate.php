<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCertificate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{ExternalParticipant, User};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zertifikat über einen abgeschlossenen Lernkurs (Feature 149, MVP-740).
 *
 * Der arbeitsschutzrechtliche Nachweis bleibt die Unterweisung im Register
 * (Feature 132) und das Soll in Feature 145 — dieses Zertifikat ist der
 * vorzeigbare Beleg, kein Ersatz dafür.
 *
 * Ein widerrufenes Zertifikat verschwindet nicht; die Prüfseite zeigt den
 * Widerruf.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int $learning_course_id
 * @property int|null $learning_course_version_id
 * @property int|null $user_id
 * @property int|null $external_participant_id
 * @property string $number
 * @property string $verification_code
 * @property string $holder_name
 * @property Carbon $issued_on
 * @property Carbon|null $valid_until
 * @property int|null $score_percent
 * @property string|null $pdf_path
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property-read LearningCourse|null $course
 */
class LearningCertificate extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_course_id',
        'learning_course_version_id',
        'user_id',
        'external_participant_id',
        'number',
        'verification_code',
        'holder_name',
        'issued_on',
        'valid_until',
        'score_percent',
        'pdf_path',
        'revoked_at',
        'revoked_reason',
        'revoked_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'score_percent' => 'integer',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExternalParticipant, $this> */
    public function externalParticipant(): BelongsTo {
        return $this->belongsTo(ExternalParticipant::class);
    }

    public function isRevoked(): bool {
        return $this->revoked_at !== null;
    }

    public function isExpired(?Carbon $on = null): bool {
        $on ??= Carbon::today();

        return $this->valid_until !== null && $this->valid_until->lessThan($on);
    }

    /** Gültig heißt: ausgestellt, nicht widerrufen, nicht abgelaufen. */
    public function isValid(?Carbon $on = null): bool {
        return ! $this->isRevoked() && ! $this->isExpired($on);
    }
}
