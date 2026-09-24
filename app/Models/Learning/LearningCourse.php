<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\{LearningAccessKind, LearningAudience, LearningCourseKind, LearningCourseStatus, LearningEnrollmentStatus, LearningInstructionSuitability, LearningTimePolicy};
use App\Models\Article\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, HasTags};
use App\Models\Hr\Qualification;
use App\Models\Platform\{Organization, User};
use App\Models\Training\TrainingCourse;
use Database\Factories\Learning\LearningCourseFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Support\Carbon;

/**
 * Lernkurs (Feature 149): die Durchführungsform einer Schulung. Das Soll
 * („wer muss was bis wann") bleibt beim Trainingskurs aus Feature 145, an
 * den dieser Kurs optional gekoppelt ist; ein Kurs ohne Pflichtbezug
 * (Kundenschulung, Verkaufskurs) hat dort NULL.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $description
 * @property string|null $objectives
 * @property string $language
 * @property LearningCourseStatus $status
 * @property list<string>|null $audiences
 * @property LearningAccessKind $access_kind
 * @property int|null $training_course_id
 * @property int|null $qualification_id
 * @property int|null $asset_id
 * @property int|null $competency_id
 * @property int|null $competency_level
 * @property int|null $article_id
 * @property int|null $owner_user_id
 * @property int|null $duration_minutes
 * @property int|null $validity_months
 * @property int $points
 * @property LearningTimePolicy $time_policy
 * @property LearningInstructionSuitability $instruction_suitability
 * @property bool $certificate_enabled
 * @property bool $lti_available
 * @property bool $creates_instruction_proof
 * @property int|null $access_days
 * @property bool $sequential
 * @property int|null $category_id
 * @property Carbon|null $available_from
 * @property Carbon|null $available_until
 * @property int|null $max_enrollments
 */
class LearningCourse extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<LearningCourseFactory> */
    use HasFactory;

    use HasSqid;
    // Schlagwörter (MVP-810): Querachse neben der gepflegten Kurskategorie.
    use HasTags;

    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'subtitle',
        'description',
        'objectives',
        'language',
        'status',
        'kind',
        'exam_for_course_id',
        'prerequisite_mode',
        'audiences',
        'access_kind',
        'training_course_id',
        'qualification_id',
        'asset_id',
        'competency_id',
        'competency_level',
        'article_id',
        'owner_user_id',
        'duration_minutes',
        'validity_months',
        'points',
        'time_policy',
        'instruction_suitability',
        'certificate_enabled',
        'creates_instruction_proof',
        'access_days',
        'sequential',
        'lti_available',
        'category_id',
        'available_from',
        'available_until',
        'max_enrollments',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningCourseStatus::class,
        'kind' => LearningCourseKind::class,
        'audiences' => 'array',
        'access_kind' => LearningAccessKind::class,
        'time_policy' => LearningTimePolicy::class,
        'instruction_suitability' => LearningInstructionSuitability::class,
        'duration_minutes' => 'integer',
        'validity_months' => 'integer',
        'points' => 'integer',
        'competency_level' => 'integer',
        'certificate_enabled' => 'boolean',
        'creates_instruction_proof' => 'boolean',
        'access_days' => 'integer',
        'sequential' => 'boolean',
        'lti_available' => 'boolean',
        'available_from' => 'date:Y-m-d',
        'available_until' => 'date:Y-m-d',
        'max_enrollments' => 'integer',
    ];

    /** Org-Schalter (MVP-786): Autoren/Bewertende sehen nur eigene Kurse. */
    public static function scopingEnabled(?Organization $organization): bool {
        return (bool) ($organization?->settings['learning']['scope_to_courses'] ?? false);
    }

    /**
     * Trainer und Bewertende dieses Kurses (MVP-786).
     *
     * @return BelongsToMany<User, $this>
     */
    public function trainers(): BelongsToMany {
        return $this->belongsToMany(User::class, 'learning_course_trainers')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Einzige Filterstelle für das Trainer-Scoping: ohne Schalter oder mit
     * `learning.manage` alles, sonst nur Kurse, die der Person gehören oder
     * an denen sie Trainer/Bewertende ist.
     *
     * @param  Builder<LearningCourse>  $query
     * @return Builder<LearningCourse>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder {
        if (! self::scopingEnabled($user->organization) || $user->isAdmin() || $user->can(\App\Enums\User\Permission::LearningManage->value)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('owner_user_id', $user->id)
                ->orWhereHas('trainers', fn (Builder $t) => $t->where('users.id', $user->id));
        });
    }

    public function isVisibleTo(User $user): bool {
        return static::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    public function isExam(): bool {
        return $this->kind === LearningCourseKind::Exam;
    }

    /**
     * Kurs, den das Bestehen dieser Prüfung anrechnet (MVP-784).
     *
     * @return BelongsTo<LearningCourse, $this>
     */
    public function examTarget(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'exam_for_course_id');
    }

    /**
     * Voraussetzungskurse (MVP-784): sperren den Start, nie die Zuweisung.
     *
     * @return BelongsToMany<LearningCourse, $this>
     */
    public function prerequisites(): BelongsToMany {
        return $this->belongsToMany(LearningCourse::class, 'learning_course_prerequisites', 'learning_course_id', 'required_course_id')
            ->withTimestamps();
    }

    /** @return HasMany<LearningCourseVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(LearningCourseVersion::class);
    }

    /** @return HasMany<LearningSection, $this> */
    public function sections(): HasMany {
        return $this->hasMany(LearningSection::class)->orderBy('position');
    }

    /** @return HasMany<LearningUnit, $this> */
    public function units(): HasMany {
        return $this->hasMany(LearningUnit::class)->orderBy('position');
    }

    /** @return BelongsTo<TrainingCourse, $this> */
    public function trainingCourse(): BelongsTo {
        return $this->belongsTo(TrainingCourse::class);
    }

    /** Gerät, an dem die Einweisung erfolgt (MVP-740). */
    /** @return BelongsTo<\App\Models\Asset\Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(\App\Models\Asset\Asset::class, 'asset_id');
    }

    /** Qualifikation, die der Abschluss verleiht bzw. verlängert (013). */
    /** @return BelongsTo<Qualification, $this> */
    public function qualification(): BelongsTo {
        return $this->belongsTo(Qualification::class);
    }

    /** Kompetenz, die der Abschluss belegt (MVP-745). */
    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo {
        return $this->belongsTo(Competency::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function currentVersion(): ?LearningCourseVersion {
        return $this->versions()->where('is_current', true)->first();
    }

    /** @return BelongsTo<LearningCourseCategory, $this> */
    public function category(): BelongsTo {
        return $this->belongsTo(LearningCourseCategory::class, 'category_id');
    }

    /**
     * Verfügbarkeitsfenster (MVP-788): steuert Katalogsichtbarkeit und
     * Selbsteinschreibung — eine Zuweisung durch die Verwaltung bleibt frei.
     */
    public function isAvailableOn(?Carbon $day = null): bool {
        $day = ($day ?? Carbon::now())->toDateString();

        if ($this->available_from !== null && $this->available_from->toDateString() > $day) {
            return false;
        }

        return $this->available_until === null || $this->available_until->toDateString() >= $day;
    }

    /** @return HasMany<LearningEnrollment, $this> */
    public function enrollments(): HasMany {
        return $this->hasMany(LearningEnrollment::class, 'learning_course_id');
    }

    /** Aktive Einschreibungen — nur sie zählen gegen die Teilnehmergrenze. */
    public function activeEnrollmentsCount(): int {
        return (int) $this->enrollments()
            ->whereNotIn('status', array_map(
                static fn (LearningEnrollmentStatus $s): string => $s->value,
                array_filter(LearningEnrollmentStatus::cases(), static fn (LearningEnrollmentStatus $s): bool => $s->isFinal()),
            ))
            ->count();
    }

    public function hasCapacity(): bool {
        return $this->max_enrollments === null || $this->activeEnrollmentsCount() < $this->max_enrollments;
    }

    /**
     * Nur Kurse, deren Fenster heute offen ist (NULL = ohne Grenze).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query, ?Carbon $day = null): Builder {
        $day = ($day ?? Carbon::now())->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', $day));
    }

    /** Zielgruppen als Enum-Liste (unbekannte Werte werden verworfen). */
    /** @return list<LearningAudience> */
    public function audienceList(): array {
        return array_values(array_filter(array_map(
            static fn (string $value): ?LearningAudience => LearningAudience::tryFrom($value),
            $this->audiences ?? []
        )));
    }

    public function servesAudience(LearningAudience $audience): bool {
        return in_array($audience, $this->audienceList(), true);
    }

    /** Inhalt bearbeitbar? Freigegeben/archiviert ist gesperrt. */
    public function isContentEditable(): bool {
        return $this->status->isEditable();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReleased(Builder $query): Builder {
        return $query->where('status', LearningCourseStatus::Released->value);
    }
}
