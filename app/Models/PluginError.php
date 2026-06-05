<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginError.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eintrag der Plugin-Fehler-Inbox.
 *
 * @property int $id
 * @property string $plugin_id
 * @property string $phase
 * @property string|null $exception_class
 * @property string $message
 * @property string|null $trace
 * @property array<string, mixed>|null $context
 * @property Carbon $occurred_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 */
class PluginError extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const PHASE_BOOT = 'boot';

    public const PHASE_RUNTIME = 'runtime';

    public const PHASE_HEALTHCHECK = 'healthcheck';

    protected $fillable = [
        'plugin_id',
        'organization_id',
        'phase',
        'exception_class',
        'message',
        'trace',
        'context',
        'occurred_at',
        'acknowledged_at',
        'acknowledged_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnacknowledged(Builder $query): Builder {
        return $query->whereNull('acknowledged_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAcknowledged(Builder $query): Builder {
        return $query->whereNotNull('acknowledged_at');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledger(): BelongsTo {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isAcknowledged(): bool {
        return $this->acknowledged_at !== null;
    }
}
