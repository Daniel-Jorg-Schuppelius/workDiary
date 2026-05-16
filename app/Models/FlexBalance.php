<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexBalance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $year
 * @property int $month
 * @property int $target_minutes
 * @property int $actual_minutes
 * @property int $balance_minutes
 * @property int $carry_over_minutes
 * @property Carbon|null $computed_at
 * @property bool $locked
 */
class FlexBalance extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'target_minutes',
        'actual_minutes',
        'balance_minutes',
        'carry_over_minutes',
        'computed_at',
        'locked',
    ];

    protected function casts(): array {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'target_minutes' => 'integer',
            'actual_minutes' => 'integer',
            'balance_minutes' => 'integer',
            'carry_over_minutes' => 'integer',
            'computed_at' => 'datetime',
            'locked' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
