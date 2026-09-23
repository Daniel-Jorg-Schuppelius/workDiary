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

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\{Builder, MassPrunable, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eintrag der Plugin-Fehler-Inbox.
 *
 * @property int $id
 * @property string $plugin_id
 * @property int|null $organization_id
 * @property string $phase
 * @property string|null $exception_class
 * @property string $message
 * @property string|null $trace
 * @property array<string, mixed>|null $context
 * @property string|null $error_hash
 * @property int $occurrences
 * @property Carbon $occurred_at
 * @property Carbon|null $last_occurred_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 */
class PluginError extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    // Audit 2026-08 (W3.3): Formulare/URLs tragen Sqids, nie rohe IDs.
    use HasSqid;
    use MassPrunable;

    public const PHASE_BOOT = 'boot';

    public const PHASE_RUNTIME = 'runtime';

    public const PHASE_HEALTHCHECK = 'healthcheck';

    /** Kompatibilitätsprüfung im geplanten Healthcheck-Lauf (Review 2026-08, A8). */
    public const PHASE_COMPATIBILITY = 'compatibility';

    /** Manuell per Admin-Button ausgelöster Check — zählt nie für Auto-Disable (E-1). */
    public const PHASE_MANUAL = 'manual';

    protected $fillable = [
        'plugin_id',
        'organization_id',
        'phase',
        'exception_class',
        'message',
        'trace',
        'context',
        'error_hash',
        'occurrences',
        'occurred_at',
        'last_occurred_at',
        'acknowledged_at',
        'acknowledged_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'context' => 'array',
        'occurrences' => 'integer',
        'occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Aufbewahrung (Review 2026-08, W2c): quittierte Fehler nach 30, offene
     * nach 90 Tagen (konfigurierbar) — `model:prune` läuft täglich im Scheduler.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder {
        $ackDays = (int) config('plugins.errors_retention_acknowledged_days', 30);
        $openDays = (int) config('plugins.errors_retention_open_days', 90);

        return static::query()->where(function (Builder $q) use ($ackDays, $openDays): void {
            $q->where(fn(Builder $sub) => $sub->whereNotNull('acknowledged_at')->where('acknowledged_at', '<', now()->subDays($ackDays)))
                ->orWhere(fn(Builder $sub) => $sub->whereNull('acknowledged_at')->where('occurred_at', '<', now()->subDays($openDays)));
        });
    }

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

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    public function isAcknowledged(): bool {
        return $this->acknowledged_at !== null;
    }
}
