<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningXapiStatement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rohes xAPI-Statement (Feature 149, MVP-743).
 *
 * Bewusst **unverändert archiviert**: was ein fremder Inhalt sendet, darf
 * nicht durch unsere Interpretation verloren gehen. Ausgewertet wird eine
 * Kopie, nie das Original.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $learning_enrollment_id
 * @property string|null $statement_id
 * @property string|null $verb
 * @property string|null $object_id
 * @property string $payload
 * @property Carbon $stored_at
 */
class LearningXapiStatement extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'statement_id',
        'verb',
        'object_id',
        'payload',
        'stored_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'stored_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function statement(): array {
        $decoded = json_decode($this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
