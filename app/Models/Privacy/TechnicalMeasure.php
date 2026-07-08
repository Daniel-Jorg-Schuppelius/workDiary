<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TechnicalMeasure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\{ImplementationStatus, MeasureCategory};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Technische/organisatorische Massnahme im zentralen TOM-Katalog (Art. 32).
 * Versioniert (Snapshot je Freigabe) und zuordenbar zu VVT/AVV; mit
 * dokumentierter Wirksamkeitspruefung.
 *
 * @property int $id
 * @property int $organization_id
 */
class TechnicalMeasure extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_technical_measures';

    protected $fillable = [
        'organization_id',
        'name',
        'category',
        'responsible_user_id',
        'implementation_status',
        'protection_level',
        'current_version_id',
        'valid_from',
        'valid_until',
        'next_review_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'category' => MeasureCategory::class,
        'implementation_status' => ImplementationStatus::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
        'next_review_at' => 'date',
    ];

    /** @return HasMany<TechnicalMeasureVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(TechnicalMeasureVersion::class, 'measure_id')->orderByDesc('version_no');
    }

    /** @return BelongsTo<TechnicalMeasureVersion, $this> */
    public function currentVersion(): BelongsTo {
        return $this->belongsTo(TechnicalMeasureVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return HasMany<MeasureAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(MeasureAssignment::class, 'measure_id');
    }

    /** @return HasMany<MeasureReview, $this> */
    public function reviews(): HasMany {
        return $this->hasMany(MeasureReview::class, 'measure_id')->latest('id');
    }

    public function isReviewOverdue(): bool {
        $due = $this->getAttribute('next_review_at');

        return $due !== null && $due->isPast();
    }

    /**
     * Nachweisanhänge (Nachtrag 043b): Zertifikate/Auditberichte mit
     * optionalem Gültig-bis — abgelaufene Nachweise meldet der
     * Compliance-Check (tom_proof_current).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<PrivacyAttachment, $this>
     */
    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany {
        return $this->morphMany(PrivacyAttachment::class, 'attachable');
    }
}
