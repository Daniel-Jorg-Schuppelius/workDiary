<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Safety;

use App\Enums\Safety\HazardAssessmentStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Safety\HazardAssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Gefährdungsbeurteilung (§ 5 ArbSchG, Feature 132): Bereich/Tätigkeit mit
 * Gefährdungs-Positionen ({@see HazardAssessmentItem}), laufender Nummer je
 * Organisation und Wiedervorlage (review_due_on → Fristen-Scan).
 *
 * Versionierung nach Protokoll-Muster: Die Freigabe friert den Stand ein
 * (Model-Guard: bei Status approved sind nur noch Statuswechsel erlaubt,
 * kein Löschen). Änderungen erzeugen über HazardAssessmentService::newVersion()
 * eine Folgeversion (gleiche assessment_no, version + 1, supersedes_id auf
 * das Original); die abgelöste Version wird archiviert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $assessment_no
 * @property int $version
 * @property int|null $supersedes_id
 * @property string $area
 * @property string|null $activity
 * @property string|null $description
 * @property HazardAssessmentStatus $status
 * @property Carbon|null $review_due_on
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property int|null $created_by_user_id
 */
class HazardAssessment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<HazardAssessmentFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'assessment_no',
        'version',
        'supersedes_id',
        'area',
        'activity',
        'description',
        'status',
        'review_due_on',
        'approved_by_user_id',
        'approved_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'assessment_no' => 'integer',
        'version' => 'integer',
        'status' => HazardAssessmentStatus::class,
        'review_due_on' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Einfrier-Guards: Ein freigegebener Stand ist Nachweis — nur noch der
     * Statuswechsel (archivieren) ist erlaubt; freigegebene/archivierte
     * Stände sind nicht löschbar (Historie statt Überschreiben).
     */
    protected static function booted(): void {
        static::updating(function (self $assessment): void {
            if ($assessment->getOriginal('status') !== HazardAssessmentStatus::Approved) {
                return;
            }
            $dirty = array_diff(array_keys($assessment->getDirty()), ['status', 'updated_at']);
            if ($dirty !== []) {
                throw ValidationException::withMessages([
                    'status' => __('safety.register.error.assessment_frozen'),
                ]);
            }
        });

        static::deleting(function (self $assessment): void {
            if (! $assessment->status->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => __('safety.register.error.assessment_frozen'),
                ]);
            }
        });
    }

    /** Anzeige-Kennung im Register (z. B. "GB-3 v2"). */
    public function displayNo(): string {
        return 'GB-' . $this->assessment_no . ' v' . $this->version;
    }

    public function isEditable(): bool {
        return $this->status->isEditable();
    }

    public function isReviewOverdue(): bool {
        return $this->review_due_on !== null && $this->review_due_on->isPast() && ! $this->review_due_on->isToday();
    }

    /** @return HasMany<HazardAssessmentItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(HazardAssessmentItem::class)->orderBy('position');
    }

    /** @return BelongsTo<self, $this> */
    public function supersedes(): BelongsTo {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasMany<self, $this> */
    public function successors(): HasMany {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<SafetyInstruction, $this> */
    public function instructions(): HasMany {
        return $this->hasMany(SafetyInstruction::class);
    }

    /**
     * Gültige (freigegebene) Stände — Basis des Wiedervorlage-Scans.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder {
        return $query->where('status', HazardAssessmentStatus::Approved->value);
    }

    /**
     * Freigegebene Stände mit erreichter/überschrittener Wiedervorlage.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReviewDue(Builder $query): Builder {
        return $query->approved()
            ->whereNotNull('review_due_on')
            ->whereDate('review_due_on', '<=', now()->toDateString());
    }
}
