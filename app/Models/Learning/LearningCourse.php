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

use App\Enums\Learning\{LearningAccessKind, LearningAudience, LearningCourseStatus, LearningInstructionSuitability, LearningTimePolicy};
use App\Models\{Article, Qualification, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Training\TrainingCourse;
use Database\Factories\Learning\LearningCourseFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

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
 * @property bool $creates_instruction_proof
 * @property int|null $access_days
 * @property bool $sequential
 */
class LearningCourse extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<LearningCourseFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'subtitle',
        'description',
        'objectives',
        'language',
        'status',
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
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningCourseStatus::class,
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
    ];

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
    /** @return BelongsTo<\App\Models\Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(\App\Models\Asset::class, 'asset_id');
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
