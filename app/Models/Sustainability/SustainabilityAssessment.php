<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityAssessment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

/**
 * ESG-Bewertung (Feature 071, MVP-225/226): versioniert — der finale
 * Stand friert Kriterien, Gewichte und Datenkontext als Snapshot ein;
 * Änderungen erzeugen neue Versionen (P2, kein stilles Umdeuten).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $subject_label
 * @property int $version
 * @property string $status
 * @property string|null $summary
 * @property string|null $total_score
 * @property string|null $rating
 * @property string|null $data_quality
 * @property array<string, mixed>|null $snapshot
 * @property int|null $assessed_by
 * @property \Illuminate\Support\Carbon|null $assessed_at
 */
class SustainabilityAssessment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'subject_type', 'subject_id', 'subject_label',
        'version', 'status', 'summary', 'total_score', 'rating',
        'data_quality', 'snapshot', 'assessed_by', 'assessed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'total_score' => 'decimal:2',
        'snapshot' => 'array',
        'assessed_at' => 'datetime',
    ];

    /** @return HasMany<SustainabilityAssessmentItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(SustainabilityAssessmentItem::class, 'assessment_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function assessor(): BelongsTo {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function isFinal(): bool {
        return $this->status === 'final';
    }
}
