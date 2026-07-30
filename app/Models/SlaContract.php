<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $customer_id
 * @property int|null $project_id
 * @property string $code
 * @property string $label
 * @property array<string, array{reaction_minutes:int, resolution_minutes:int}> $priority_table
 * @property array<int, array{from:string, to:string}>|null $business_hours
 * @property array<int, array{after_minutes:int, notify:string}>|null $escalation_chain
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlaContract extends Model {
    use Auditable, BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\SlaContractFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'pause_rules',
        'organization_id',
        'customer_id',
        'project_id',
        'code',
        'label',
        'priority_table',
        'business_hours',
        'escalation_chain',
        'is_default',
        'is_active',
        'is_ola',
        'ola_team_id',
    ];

    protected $casts = [
        'pause_rules' => 'array',
        'priority_table' => 'array',
        'business_hours' => 'array',
        'escalation_chain' => 'array',
        'is_default' => 'bool',
        'is_ola' => 'bool',
        'is_active' => 'bool',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Optionale Projektbindung (W5.4): der Vertrag gilt dann nur für dieses
     * Projekt und gewinnt bei der Auflösung vor Kunden-/Default-Vertrag.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SlaContractQuota, $this> */
    public function quotas(): HasMany {
        return $this->hasMany(SlaContractQuota::class);
    }

    /**
     * Wartungspläne, die eine Vertragspflicht dieses SLA-Vertrags abbilden
     * (Feature 010 → Rang 43).
     *
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany {
        return $this->hasMany(MaintenancePlan::class);
    }
}
