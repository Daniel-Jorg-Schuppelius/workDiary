<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractNegotiation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\{Approval, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany, MorphTo};

/**
 * Vertragsverhandlung (Feature 068, MVP-195): eigener, versionierter
 * Vorgang zwischen Gewinn-/Zusageentscheidung und operativer Übergabe.
 * Hängt per Morph an ApplicationOpportunity ODER JobApplication;
 * Freigaben laufen über das bestehende Approval-Modell (mehrstufig,
 * Selbstfreigabe-Sperre).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $negotiable_type
 * @property int $negotiable_id
 * @property string $title
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property int|null $responsible_user_id
 * @property string|null $decision
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decision_note
 * @property int|null $created_by
 */
class ApplicationContractNegotiation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['draft', 'in_review', 'counter', 'approved', 'concluded', 'declined'];

    protected $fillable = [
        'organization_id', 'negotiable_type', 'negotiable_id', 'title', 'status',
        'due_on', 'responsible_user_id', 'decision', 'decided_by', 'decided_at',
        'decision_note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_on' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function negotiable(): MorphTo {
        return $this->morphTo();
    }

    /** @return HasMany<ApplicationContractVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(ApplicationContractVersion::class, 'negotiation_id')->orderByDesc('version');
    }

    /** @return HasMany<ApplicationContractReview, $this> */
    public function reviewItems(): HasMany {
        return $this->hasMany(ApplicationContractReview::class, 'negotiation_id');
    }

    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function isDecided(): bool {
        return $this->decision !== null;
    }

    /** Offene Blocker verhindern den Abschluss (MVP-196: Abweichungen sichtbar entscheiden). */
    public function hasOpenBlockers(): bool {
        return $this->reviewItems()->where('severity', 'blocker')->where('status', 'open')->exists();
    }
}
