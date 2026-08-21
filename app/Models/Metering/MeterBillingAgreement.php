<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterBillingAgreement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Metering;

use App\Models\{Asset, Customer, Project, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Abrechnungsvereinbarung je Kunde + Asset (Feature 116, MVP-605).
 *
 * @property Carbon $next_run_on
 * Die Staffel kommt aus JSON und ist NICHT formgeprüft — der Rechner
 * normalisiert sie defensiv, statt sich auf eine Struktur zu verlassen, die
 * ein Import oder eine Altdaten-Zeile nicht garantiert.
 *
 * @property array<int, mixed>|null $tiers
 */
class MeterBillingAgreement extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'asset_id',
        'project_id',
        'title',
        'base_price',
        'unit_price',
        'free_units',
        'tiers',
        'unit',
        'interval_unit',
        'interval_count',
        'next_run_on',
        'last_run_on',
        'end_on',
        'status',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tiers' => 'array',
        'next_run_on' => 'date',
        'last_run_on' => 'date',
        'end_on' => 'date',
        'interval_count' => 'integer',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'interval_unit' => 'monthly', 'interval_count' => 1];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MeterBillingRun, $this> */
    public function runs(): HasMany {
        return $this->hasMany(MeterBillingRun::class)->orderByDesc('period_start');
    }

    public function isRunnable(): bool {
        return $this->status === self::STATUS_ACTIVE
            && ($this->end_on === null || ! $this->end_on->isPast());
    }

    /** Periodenlänge in Monaten. */
    public function periodMonths(): int {
        $factor = match ($this->interval_unit) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };

        return max(1, $factor * max(1, (int) $this->interval_count));
    }
}
