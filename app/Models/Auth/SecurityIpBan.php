<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityIpBan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\Concerns\HasSqid;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Temporäre IP-Sperre (MVP-450), installationsweit wie die Angriffserkennung.
 *
 * @property int $id
 * @property string $ip
 * @property int $level
 * @property string $reason
 * @property Carbon $banned_until
 * @property Carbon|null $released_at
 * @property int|null $released_by
 */
class SecurityIpBan extends Model {
    use HasSqid;

    protected $fillable = ['ip', 'level', 'reason', 'banned_until', 'released_at', 'released_by'];

    /** @var array<string, string> */
    protected $casts = [
        'level' => 'integer',
        'banned_until' => 'datetime',
        'released_at' => 'datetime',
    ];

    /**
     * @param  Builder<SecurityIpBan>  $query
     * @return Builder<SecurityIpBan>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->whereNull('released_at')->where('banned_until', '>', now());
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by');
    }
}
