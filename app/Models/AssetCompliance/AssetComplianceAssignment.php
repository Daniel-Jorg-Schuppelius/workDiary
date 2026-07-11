<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Models\{Asset, ExternalContact, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Prüfpflicht eines Assets (MVP-284): Profil + Intervall-Override,
 * Fälligkeit, Verantwortliche und Prüfstelle. Die Sperrwirkung kommt aus
 * dem Profil (blocking_mode) und wirkt über das gemeinsame Modell (D12).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_compliance_profile_id
 * @property int $asset_id
 * @property int|null $interval_months_override
 * @property \Illuminate\Support\Carbon|null $last_done_on
 * @property \Illuminate\Support\Carbon|null $next_due_on
 * @property bool $is_active
 */
class AssetComplianceAssignment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_compliance_profile_id', 'asset_id',
        'interval_months_override', 'last_done_on', 'next_due_on',
        'responsible_user_id', 'external_contact_id', 'is_active', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'interval_months_override' => 'integer',
        'last_done_on' => 'date',
        'next_due_on' => 'date',
        'is_active' => 'boolean',
    ];

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->where('is_active', true);
    }

    public function intervalMonths(): int {
        return $this->interval_months_override ?? (int) ($this->profile->interval_months ?? 12);
    }

    public function isOverdue(): bool {
        if ($this->next_due_on === null) {
            return false;
        }

        $tolerance = (int) ($this->profile->tolerance_days ?? 0);

        return $this->next_due_on->copy()->addDays($tolerance)->endOfDay()->isPast();
    }

    public function isDueSoon(): bool {
        if ($this->next_due_on === null || $this->isOverdue()) {
            return false;
        }

        $warn = (int) ($this->profile->warn_days_before ?? 30);

        return $this->next_due_on->copy()->subDays($warn)->startOfDay()->isPast();
    }

    /**
     * Nachfrist abgelaufen → Sperre gemäß blocking_mode (MVP-284/288).
     */
    public function isPastGrace(): bool {
        if ($this->next_due_on === null) {
            return false;
        }

        $tolerance = (int) ($this->profile->tolerance_days ?? 0);
        $grace = (int) ($this->profile->grace_days ?? 0);

        return $this->next_due_on->copy()->addDays($tolerance + $grace)->endOfDay()->isPast();
    }

    /** @return BelongsTo<AssetComplianceProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(AssetComplianceProfile::class, 'asset_compliance_profile_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<ExternalContact, $this> */
    public function externalContact(): BelongsTo {
        return $this->belongsTo(ExternalContact::class);
    }

    /** @return HasMany<AssetInspectionSchedule, $this> */
    public function schedules(): HasMany {
        return $this->hasMany(AssetInspectionSchedule::class);
    }

    /** @return HasMany<AssetInspectionEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(AssetInspectionEvent::class)->latest('performed_at');
    }
}
