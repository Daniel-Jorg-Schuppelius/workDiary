<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCourse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Training;

use App\Enums\Training\TrainingProviderKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use Database\Factories\Training\TrainingCourseFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Kurs im Schulungskatalog (Feature 145): Titel, Anbieter, Dauer,
 * Gültigkeit in Monaten, Pflicht ja/nein, Rechtsgrundlage und Kosten
 * (rein informativ — keine Buchung). Lerninhalte/LMS bleiben außen vor.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $title
 * @property TrainingProviderKind $provider_kind
 * @property string|null $provider_name
 * @property int|null $duration_minutes
 * @property int|null $validity_months
 * @property bool $is_mandatory
 * @property string|null $legal_basis
 * @property string|null $cost_amount
 * @property CurrencyCode|null $cost_currency
 * @property int $lead_days
 * @property string|null $notes
 * @property bool $is_active
 * @property string $source
 * @property int|null $created_by_user_id
 */
class TrainingCourse extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<TrainingCourseFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'provider_kind',
        'provider_name',
        'duration_minutes',
        'validity_months',
        'is_mandatory',
        'legal_basis',
        'cost_amount',
        'cost_currency',
        'lead_days',
        'notes',
        'is_active',
        'source',
        'created_by_user_id',
    ];

    protected $casts = [
        'provider_kind' => TrainingProviderKind::class,
        'duration_minutes' => 'integer',
        'validity_months' => 'integer',
        'is_mandatory' => 'boolean',
        'cost_currency' => CurrencyCode::class,
        'lead_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Vorlauf der Fälligkeitsmeldung in Tagen (nie negativ). */
    public function leadDays(): int {
        return max(0, (int) $this->lead_days);
    }

    /** @return HasMany<TrainingCourseVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(TrainingCourseVersion::class);
    }

    /** @return HasMany<TrainingRequirement, $this> */
    public function requirements(): HasMany {
        return $this->hasMany(TrainingRequirement::class);
    }

    /** @return HasMany<TrainingAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(TrainingAssignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function currentVersion(): ?TrainingCourseVersion {
        return $this->versions()->where('is_current', true)->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
