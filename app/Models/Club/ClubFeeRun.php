<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\ClubFeeRunStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Beitragslauf eines Abrechnungsmonats (MVP-850): eingefrorene Vorschau mit
 * Positionen und Fehlern; die Freigabe erzeugt die Forderungen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $year
 * @property int $month
 * @property ClubFeeRunStatus $status
 * @property list<array<string, mixed>>|null $positions
 * @property list<array<string, mixed>>|null $issues
 * @property Money $total
 * @property CurrencyCode $currency
 * @property int $claims_count
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property Carbon|null $calculated_at
 * @property Carbon|null $released_at
 * @property int|null $released_by_user_id
 */
class ClubFeeRun extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'year', 'month', 'status', 'positions', 'issues', 'total', 'currency', 'claims_count', 'notes', 'created_by_user_id', 'calculated_at', 'released_at', 'released_by_user_id'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'status' => ClubFeeRunStatus::class,
        'positions' => 'array',
        'issues' => 'array',
        'total' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'claims_count' => 'integer',
        'calculated_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /** @return HasMany<ClubFeeClaim, $this> */
    public function claims(): HasMany {
        return $this->hasMany(ClubFeeClaim::class);
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function isDraft(): bool {
        return $this->status === ClubFeeRunStatus::Draft;
    }

    public function hasIssues(): bool {
        return ($this->issues ?? []) !== [];
    }

    public function monthLabel(): string {
        return \Carbon\CarbonImmutable::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');
    }
}
