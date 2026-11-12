<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningVideoProgress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Learning;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gesehener Anteil eines Kursvideos je Einschreibung (Feature 149, MVP-1598).
 * Abschlusskriterium, kein Verhaltensprofil: nur Summe und Anteil.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int $learning_unit_id
 * @property int $attachment_id
 * @property int $watched_seconds
 * @property int $watched_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LearningVideoProgress extends Model {
    use BelongsToOrganization;

    protected $table = 'learning_video_progress';

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_unit_id',
        'attachment_id',
        'watched_seconds',
        'watched_percent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'watched_seconds' => 'integer',
        'watched_percent' => 'integer',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }
}
