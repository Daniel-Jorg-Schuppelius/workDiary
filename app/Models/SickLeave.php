<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeave.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Sickness\SickLeaveKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Services\HolidayService;
use Carbon\{Carbon, CarbonInterface};
use Database\Factories\SickLeaveFactory;
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property SickLeaveKind $kind
 * @property int|null $follow_up_for_id
 * @property string|null $au_number
 * @property string|null $doctor_name
 * @property string|null $note
 * @property Carbon|null $kasse_notified_at
 * @property Carbon|null $reported_at
 * @property int|null $recorded_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SickLeave extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<SickLeaveFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'start_date',
        'end_date',
        'kind',
        'follow_up_for_id',
        'au_number',
        'doctor_name',
        'note',
        'kasse_notified_at',
        'reported_at',
        'recorded_by',
        'cancelled_at',
        'cancel_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'kind' => SickLeaveKind::class,
        'kasse_notified_at' => 'datetime',
        'reported_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<SickLeave, $this> */
    public function followUpFor(): BelongsTo {
        return $this->belongsTo(SickLeave::class, 'follow_up_for_id');
    }

    /** @return HasMany<SickLeave, $this> */
    public function followUps(): HasMany {
        return $this->hasMany(SickLeave::class, 'follow_up_for_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** @param Builder<SickLeave> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('cancelled_at');
    }

    /** @param Builder<SickLeave> $query */
    public function scopeForUser(Builder $query, int $userId): void {
        $query->where('user_id', $userId);
    }

    /** @param Builder<SickLeave> $query */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void {
        $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }

    /** @param Builder<SickLeave> $query */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): void {
        $query->whereNull('cancelled_at')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Werktage (Mo–Fr, ohne Feiertage). */
    public function workingDays(HolidayService $holidayService): int {
        return $holidayService->workingDaysBetween($this->start_date, $this->end_date);
    }

    /** Kalendertage (inklusive Start- und Endtag). */
    public function calendarDays(): int {
        return (int) $this->start_date->startOfDay()->diffInDays($this->end_date->startOfDay()) + 1;
    }

    /** Lückenlose Krankheitskette: klettert über follow_up_for_id bis zur Wurzel. */
    public function chainStart(): SickLeave {
        $node = $this;
        $visited = [];
        while ($node->follow_up_for_id !== null) {
            if (isset($visited[$node->id])) {
                break;
            }
            $visited[$node->id] = true;
            $parent = $node->followUpFor;
            if (! $parent instanceof SickLeave) {
                break;
            }
            $node = $parent;
        }

        return $node;
    }

    /**
     * Alle Einträge der Kette (Start gefolgt von allen rekursiven Follow-Ups), sortiert nach start_date.
     *
     * @return Collection<int, SickLeave>
     */
    public function chain(): Collection {
        $root = $this->chainStart();

        /** @var Collection<int, SickLeave> $all */
        $all = new Collection([$root]);
        $stack = [$root];
        while ($stack !== []) {
            /** @var SickLeave $current */
            $current = array_pop($stack);
            /** @var Collection<int, SickLeave> $followUps */
            $followUps = $current->followUps()->get();
            foreach ($followUps as $follow) {
                $all->push($follow);
                $stack[] = $follow;
            }
        }

        return $all->sortBy(fn(SickLeave $s) => $s->start_date->getTimestamp())->values();
    }

    public function kindLabel(): string {
        return $this->kind->label();
    }

    public function isCancelled(): bool {
        return $this->cancelled_at !== null;
    }
}
