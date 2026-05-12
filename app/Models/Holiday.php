<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Carbon\Carbon|null $date
 * @property bool                $is_recurring
 * @property string              $recurrence_type
 * @property int|null            $recurrence_weekday
 * @property int|null            $recurrence_week
 * @property int|null            $recurrence_month
 * @property string              $name
 */
class Holiday extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'date',
        'name',
        'is_recurring',
        'recurrence_type',
        'recurrence_weekday',
        'recurrence_week',
        'recurrence_month',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array {
        return [
            'date'               => 'date',
            'is_recurring'       => 'boolean',
            'recurrence_weekday' => 'integer',
            'recurrence_week'    => 'integer',
            'recurrence_month'   => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Gibt alle Datums-Strings (Y-m-d) zurück, die dieser Feiertag im Zieljahr belegt.
     *
     * @return list<string>
     */
    public function resolveForYear(int $year): array {
        if (($this->recurrence_type ?? 'fixed') === 'relative') {
            $months = $this->recurrence_month ? [$this->recurrence_month] : range(1, 12);
            $dates  = [];
            foreach ($months as $month) {
                try {
                    $base = Carbon::create($year, (int) $month, 1);
                    $date = ($this->recurrence_week === -1)
                        ? $base->lastOfMonth($this->recurrence_weekday ?? 0)
                        : $base->nthOfMonth($this->recurrence_week ?? 1, $this->recurrence_weekday ?? 0);
                    $dates[] = $date->format('Y-m-d');
                } catch (\Throwable) {
                    // Ungültige Vorkommen (z. B. 5. Montag in einem Monat mit nur 4)
                }
            }
            return $dates;
        }

        // Jährlich, festes Datum – Monat/Tag ins Zieljahr projizieren
        if ($this->is_recurring) {
            if (! $this->date) {
                return [];
            }
            $month = (int) $this->date->format('m');
            $day   = (int) $this->date->format('d');
            if (! checkdate($month, $day, $year)) {
                return [];
            }
            return [sprintf('%04d-%02d-%02d', $year, $month, $day)];
        }

        // Einmalig – exaktes Datum
        if ($this->date && (int) $this->date->format('Y') === $year) {
            return [$this->date->format('Y-m-d')];
        }

        return [];
    }

    /** Lesbarer Label für die Index-Ansicht. */
    public function recurrenceLabel(): string {
        if (($this->recurrence_type ?? 'fixed') === 'relative') {
            $weeks  = [1 => '1.', 2 => '2.', 3 => '3.', 4 => '4.', -1 => 'Letzter'];
            $days   = [0 => 'So', 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa'];
            $months = [
                1 => 'Jan',
                2 => 'Feb',
                3 => 'Mär',
                4 => 'Apr',
                5 => 'Mai',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Aug',
                9 => 'Sep',
                10 => 'Okt',
                11 => 'Nov',
                12 => 'Dez'
            ];

            $w = $weeks[$this->recurrence_week ?? 1]    ?? '?';
            $d = $days[$this->recurrence_weekday ?? 0]   ?? '?';
            $m = $this->recurrence_month ? ($months[$this->recurrence_month] ?? '?') : 'monatl.';

            return "{$w} {$d} – {$m}";
        }

        if ($this->is_recurring) {
            return 'Jährl. · ' . ($this->date?->format('d.m.') ?? '?');
        }

        return 'Einmalig';
    }
}
