<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerGeofence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Location;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, Project, Site};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $site_id
 * @property int|null $project_id
 * @property string $label
 * @property string $center_lat
 * @property string $center_lng
 * @property int $radius_m
 * @property int $min_dwell_minutes
 * @property int $gap_merge_minutes
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerGeofence extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'site_id',
        'project_id',
        'label',
        'center_lat',
        'center_lng',
        'radius_m',
        'min_dwell_minutes',
        'gap_merge_minutes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'radius_m' => 'integer',
        'min_dwell_minutes' => 'integer',
        'gap_merge_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @param Builder<CustomerGeofence> $query */
    public function scopeActive(Builder $query): void {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<LocationVisit, $this> */
    public function visits(): HasMany {
        return $this->hasMany(LocationVisit::class);
    }

    /**
     * Zielprojekt für Buchungen: explizit gesetzt, sonst Standardprojekt des
     * Kunden (lazy angelegt).
     */
    public function resolveProject(): Project {
        if ($this->project_id !== null && $this->project instanceof Project) {
            return $this->project;
        }

        $customer = $this->customer;
        if (! $customer instanceof Customer) {
            throw new \RuntimeException('CustomerGeofence ohne Kunde kann kein Zielprojekt auflösen.');
        }

        return $customer->defaultProjectOrCreate();
    }
}
