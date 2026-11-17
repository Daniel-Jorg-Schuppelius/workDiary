<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Services\Delegation\ActsForResolver;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Database\Factories\Delegation\WorkDelegationFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vertretung bei Abwesenheit (Feature 202, MVP-1647): `deputy_user_id`
 * vertritt `represented_user_id` vom `valid_from` bis `valid_until`
 * (einschließlich; ohne Ende bis „Vertretung beenden“). Wirkt nur beim Lesen
 * über {@see ActsForResolver}; Datensätze und Rechte bleiben unverändert.
 * Angelegt und beendet wird über
 * {@see \App\Services\Delegation\WorkDelegationService}; Audit `created`,
 * `work_delegation.ended`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $represented_user_id
 * @property int $deputy_user_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property WorkDelegationKind $kind
 * @property string|null $absence_type
 * @property int|null $absence_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $ended_at
 * @property int|null $ended_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkDelegation extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<WorkDelegationFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'represented_user_id',
        'deputy_user_id',
        'valid_from',
        'valid_until',
        'kind',
        'absence_type',
        'absence_id',
        'note',
        'created_by',
        'ended_at',
        'ended_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'kind' => WorkDelegationKind::class,
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void {
        // Der Resolver merkt sich die Vertretungen je Anfrage; jede Änderung verwirft das.
        $flush = static function (): void {
            if (app()->resolved(ActsForResolver::class)) {
                app(ActsForResolver::class)->flush();
            }
        };
        static::saved($flush);
        static::deleted($flush);
    }

    /** @return BelongsTo<User, $this> */
    public function represented(): BelongsTo {
        return $this->belongsTo(User::class, 'represented_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function deputy(): BelongsTo {
        return $this->belongsTo(User::class, 'deputy_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function ender(): BelongsTo {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /**
     * Am Tag wirksam: nicht beendet, begonnen und nicht abgelaufen. DATE-Grenzen
     * halboffen gegen den Folgetag (SQLite speichert `Y-m-d 00:00:00`).
     *
     * @param  Builder<WorkDelegation>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $day): void {
        $query->whereNull('ended_at')
            ->where('valid_from', '<', DateRange::dayAfter($day))
            ->where(static fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($day)));
    }

    /**
     * Noch nicht vorbei: nicht beendet und nicht abgelaufen (wirksam oder geplant).
     *
     * @param  Builder<WorkDelegation>  $query
     */
    public function scopeCurrentOrUpcoming(Builder $query, CarbonInterface $day): void {
        $query->whereNull('ended_at')
            ->where(static fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($day)));
    }

    public function isEnded(): bool {
        return $this->ended_at !== null;
    }

    /** Zustand am Tag für die Anzeige: `ended`, `upcoming`, `active` oder `past`. */
    public function stateOn(CarbonInterface $day): string {
        $day = $day->copy()->startOfDay();

        return match (true) {
            $this->ended_at !== null => 'ended',
            $this->valid_from->copy()->startOfDay()->greaterThan($day) => 'upcoming',
            $this->valid_until !== null && $this->valid_until->copy()->startOfDay()->lessThan($day) => 'past',
            default => 'active',
        };
    }

    /**
     * Das Beenden erscheint im Audit als eigenes Ereignis statt als „Geändert“.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        if ($event === 'updated' && array_key_exists('ended_at', $changes) && $changes['ended_at'] !== null) {
            return 'work_delegation.ended';
        }

        return $event;
    }
}
