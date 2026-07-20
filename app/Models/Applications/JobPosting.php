<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobPosting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Str;

/**
 * Veröffentlichungskanal einer Stellenausschreibung (Feature 068, MVP-189).
 *
 * MVP-437: Der `website`-Kanal ist zugleich die öffentliche Karriereseite —
 * er trägt einen stabilen `public_slug`, getrennte öffentliche Inhaltsfelder
 * (nie interne Budget-/Profil-/Pipeline-Daten) und den Lifecycle
 * draft|published|paused|expired|closed.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_requisition_id
 * @property string|null $public_slug
 * @property string|null $public_title
 * @property string|null $public_summary
 * @property string|null $public_description
 * @property string|null $public_tasks
 * @property string|null $public_requirements
 * @property string|null $public_benefits
 * @property string|null $work_location
 * @property \Illuminate\Support\Carbon|null $application_deadline
 * @property string $channel
 * @property string|null $reference
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $status
 * @property-read JobRequisition|null $requisition
 */
class JobPosting extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const CHANNELS = ['website', 'portal', 'agency', 'social', 'print', 'referral', 'other'];

    // MVP-437: 'paused' ergänzt — pausierte Veröffentlichungen sind sichtbar
    // (Vorschau), aber nicht bewerbbar.
    public const STATUSES = ['draft', 'published', 'paused', 'expired', 'closed'];

    protected $fillable = [
        'organization_id', 'job_requisition_id', 'channel', 'reference', 'url',
        'published_at', 'expires_at', 'status',
        'public_slug', 'public_title', 'public_summary', 'public_description',
        'public_tasks', 'public_requirements', 'public_benefits',
        'work_location', 'application_deadline',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'date',
        'application_deadline' => 'date',
    ];

    /** @return BelongsTo<JobRequisition, $this> */
    public function requisition(): BelongsTo {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    /** @return HasMany<JobApplication, $this> */
    public function applications(): HasMany {
        return $this->hasMany(JobApplication::class, 'job_posting_id');
    }

    /**
     * Öffentlich gelistete Veröffentlichungen (Karriere-Liste): freigegeben,
     * mit Slug — pausierte/geschlossene/abgelaufene bleiben außen vor.
     *
     * @param  Builder<JobPosting>  $query
     * @return Builder<JobPosting>
     */
    public function scopePubliclyListed(Builder $query): Builder {
        return $query->where('status', 'published')->whereNotNull('public_slug');
    }

    /**
     * Öffentlich auffindbar (Detail/Vorschau): freigegeben ODER pausiert. Der
     * Bewerbungsknopf richtet sich zusätzlich nach {@see isApplyable()}.
     *
     * @param  Builder<JobPosting>  $query
     * @return Builder<JobPosting>
     */
    public function scopePublicResolvable(Builder $query): Builder {
        return $query->whereIn('status', ['published', 'paused'])->whereNotNull('public_slug');
    }

    /**
     * Bewerbbar: freigegeben und weder pausiert/geschlossen noch nach
     * Bewerbungsschluss/Ablauf.
     */
    public function isApplyable(): bool {
        if ($this->status !== 'published') {
            return false;
        }
        $today = CarbonImmutable::today();
        if ($this->application_deadline !== null && $this->application_deadline->lt($today)) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->lt($today)) {
            return false;
        }

        return true;
    }

    /**
     * Nur die freigegebenen öffentlichen Inhalte — Views/Embed dürfen nie auf
     * interne Requisition-Felder (Budget, Profil, Verantwortliche) zugreifen.
     *
     * @return array<string, string|null>
     */
    public function publicPayload(): array {
        return [
            'title' => $this->public_title,
            'summary' => $this->public_summary,
            'description' => $this->public_description,
            'tasks' => $this->public_tasks,
            'requirements' => $this->public_requirements,
            'benefits' => $this->public_benefits,
            'location' => $this->work_location,
            'deadline' => $this->application_deadline?->format('Y-m-d'),
        ];
    }

    /**
     * Erzeugt einen je Organisation eindeutigen, stabilen öffentlichen Slug aus
     * dem Titel. Kollisionen werden mit einem Zähler-Suffix aufgelöst.
     */
    public function ensurePublicSlug(string $fromTitle): string {
        if (is_string($this->public_slug) && $this->public_slug !== '') {
            return $this->public_slug;
        }

        $base = Str::slug($fromTitle) ?: 'stelle';
        $slug = $base;
        $suffix = 1;
        while (static::query()
            ->where('organization_id', $this->organization_id)
            ->where('public_slug', $slug)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->exists()
        ) {
            $slug = $base . '-' . (++$suffix);
        }

        return $slug;
    }
}
