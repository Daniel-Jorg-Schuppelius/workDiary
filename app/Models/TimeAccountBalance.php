<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountBalance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materialisierter Monatsstand eines Zeitkontos (MVP-526) — reine
 * Performance-Ableitung aus dem Journal, jederzeit neu aufbaubar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $time_account_id
 * @property int $user_id
 * @property int $year
 * @property int $month
 * @property string $turnover
 * @property string $balance
 */
class TimeAccountBalance extends Model {
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'time_account_id',
        'user_id',
        'year',
        'month',
        'turnover',
        'balance',
        'computed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'turnover' => 'decimal:2',
        'balance' => 'decimal:2',
        'computed_at' => 'datetime',
    ];

    /** @return BelongsTo<TimeAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(TimeAccount::class, 'time_account_id');
    }
}
