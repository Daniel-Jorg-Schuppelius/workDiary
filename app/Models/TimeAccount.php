<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeAccount\{CarryoverPolicy, TimeAccountUnit};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Konfigurierbares Zusatz-Zeitkonto (MVP-526): Schichtkonten, Freizeit-/
 * Ansparkonten, Zähler. Gleitzeit und Urlaub bleiben die bestehenden
 * Spezialkonten (FlexBalance/VacationEntitlement) — keine Doppelführung.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property TimeAccountUnit $unit
 * @property string|null $warn_threshold
 * @property string|null $critical_threshold
 * @property CarryoverPolicy $carryover_policy
 * @property string|null $cap_amount
 * @property bool $show_on_terminal
 * @property bool $is_active
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 */
class TimeAccount extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'unit',
        'warn_threshold',
        'critical_threshold',
        'carryover_policy',
        'cap_amount',
        'show_on_terminal',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'unit' => TimeAccountUnit::class,
        'carryover_policy' => CarryoverPolicy::class,
        'show_on_terminal' => 'boolean',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return HasMany<TimeAccountRule, $this> */
    public function rules(): HasMany {
        return $this->hasMany(TimeAccountRule::class);
    }

    /** @return HasMany<TimeAccountEntry, $this> */
    public function entries(): HasMany {
        return $this->hasMany(TimeAccountEntry::class);
    }

    /** @return HasMany<TimeAccountBalance, $this> */
    public function balances(): HasMany {
        return $this->hasMany(TimeAccountBalance::class);
    }

    /** Ampel-Ton für einen Kontostand (success | warning | error). */
    public function tone(float $balance): string {
        $critical = $this->critical_threshold !== null ? (float) $this->critical_threshold : null;
        $warn = $this->warn_threshold !== null ? (float) $this->warn_threshold : null;
        $abs = abs($balance);

        if ($critical !== null && $abs >= $critical) {
            return 'error';
        }
        if ($warn !== null && $abs >= $warn) {
            return 'warning';
        }

        return 'success';
    }
}
