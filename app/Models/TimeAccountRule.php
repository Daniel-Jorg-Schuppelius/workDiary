<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeAccount\TimeAccountSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bebuchungsregel eines Zeitkontos (MVP-526): Quelle + Match + Faktor.
 * Mandantengrenze transitiv über das Konto (cascade).
 *
 * @property int $id
 * @property int $time_account_id
 * @property TimeAccountSource $source_type
 * @property string|null $match_value
 * @property string $factor
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 */
class TimeAccountRule extends Model {
    protected $fillable = [
        'time_account_id',
        'source_type',
        'match_value',
        'factor',
        'valid_from',
        'valid_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source_type' => TimeAccountSource::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<TimeAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(TimeAccount::class, 'time_account_id');
    }

    public function appliesOn(\DateTimeInterface $date): bool {
        $d = $date->format('Y-m-d');
        if ($this->valid_from !== null && $this->valid_from->format('Y-m-d') > $d) {
            return false;
        }

        return $this->valid_until === null || $this->valid_until->format('Y-m-d') >= $d;
    }
}
