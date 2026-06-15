<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AvailabilityWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Shift\AvailabilityKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Database\Factories\AvailabilityWindowFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Verfügbarkeit/Wunschfenster eines Mitarbeiters (Feature 007).
 *
 * Wiederkehrend über `weekday` (0=So … 6=Sa) ODER datumsbezogen über
 * `specific_date`. `valid_from`/`valid_until` begrenzen wiederkehrende
 * Fenster optional. NULL bei start_time/end_time bedeutet ganztägig.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int|null $weekday
 * @property Carbon|null $specific_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property AvailabilityKind $kind
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AvailabilityWindow extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<AvailabilityWindowFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'weekday',
        'specific_date',
        'start_time',
        'end_time',
        'kind',
        'valid_from',
        'valid_until',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'weekday' => 'integer',
        'specific_date' => 'date:Y-m-d',
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'kind' => AvailabilityKind::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Gilt dieses Fenster an dem gegebenen Datum (Wochentag + Gültigkeit)?
     */
    public function appliesToDate(\DateTimeInterface $date): bool {
        if ($this->specific_date !== null) {
            return $this->specific_date->isSameDay($date);
        }
        if ($this->weekday === null) {
            return false;
        }
        if ((int) $date->format('w') !== $this->weekday) {
            return false;
        }
        if ($this->valid_from !== null && $this->valid_from->startOfDay()->gt($date)) {
            return false;
        }
        if ($this->valid_until !== null && $this->valid_until->endOfDay()->lt($date)) {
            return false;
        }

        return true;
    }

    /**
     * Verfügbarkeitsfenster, die an dem gegebenen Datum greifen können.
     *
     * @param  Builder<AvailabilityWindow>  $query
     * @return Builder<AvailabilityWindow>
     */
    public function scopeForDate(Builder $query, \DateTimeInterface $date): Builder {
        $weekday = (int) $date->format('w');
        $dateStr = $date->format('Y-m-d');

        return $query->where(function (Builder $q) use ($weekday, $dateStr): void {
            $q->where('specific_date', $dateStr)
                ->orWhere(function (Builder $q2) use ($weekday, $dateStr): void {
                    $q2->whereNull('specific_date')
                        ->where('weekday', $weekday)
                        ->where(function (Builder $q3) use ($dateStr): void {
                            $q3->whereNull('valid_from')->orWhere('valid_from', '<=', $dateStr);
                        })
                        ->where(function (Builder $q4) use ($dateStr): void {
                            $q4->whereNull('valid_until')->orWhere('valid_until', '>=', $dateStr);
                        });
                });
        });
    }

    /**
     * @param  Builder<AvailabilityWindow>  $query
     * @return Builder<AvailabilityWindow>
     */
    public function scopeForUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }
}
