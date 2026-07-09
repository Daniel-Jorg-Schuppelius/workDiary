<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, HasSqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Geplantes Wartungsfenster (MVP-055). Wirksam ist ein Fenster rein
 * ZEITBASIERT (starts_at–ends_at bei nicht-terminalem Status) — die
 * Sperre greift damit sekundengenau ohne Cron-Abhängigkeit; das
 * Statusfeld dokumentiert den Lebenszyklus (operations:scan führt
 * announced→active→completed nach und meldet die Ankündigung).
 *
 * @property int $id
 * @property string $scope system|organization
 * @property int|null $organization_id
 * @property CarbonImmutable|null $announce_from
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string|null $message
 * @property bool $read_only
 * @property bool $block_ingest
 * @property string $status
 * @property int|null $created_by
 * @property string|null $notes
 */
class MaintenanceWindow extends Model {
    use Auditable;
    use HasSqid;

    public const SCOPE_SYSTEM = 'system';

    public const SCOPE_ORGANIZATION = 'organization';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_ANNOUNCED = 'announced';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXTENDED = 'extended';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_CANCELLED = 'cancelled';

    /** Nicht-terminale Status — nur diese können wirksam werden. */
    public const OPEN_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ANNOUNCED,
        self::STATUS_ACTIVE,
        self::STATUS_EXTENDED,
    ];

    public const CACHE_KEY = 'maintenance.windows.open';

    protected $table = 'maintenance_windows';

    protected $fillable = [
        'scope',
        'organization_id',
        'announce_from',
        'starts_at',
        'ends_at',
        'message',
        'read_only',
        'block_ingest',
        'status',
        'created_by',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'announce_from' => 'immutable_datetime',
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'read_only' => 'boolean',
        'block_ingest' => 'boolean',
    ];

    protected static function booted(): void {
        $flush = static fn() => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function isEffectiveNow(): bool {
        return in_array($this->status, self::OPEN_STATUSES, true)
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    public function isAnnouncedUpcoming(): bool {
        $announceFrom = $this->announce_from ?? $this->starts_at->subDay();

        return in_array($this->status, [self::STATUS_PLANNED, self::STATUS_ANNOUNCED], true)
            && $this->starts_at->isFuture()
            && $announceFrom->isPast();
    }

    /**
     * Offene Fenster, gecacht (Middleware-Hot-Path) und DB-ausfallsicher.
     *
     * @return Collection<int, self>
     */
    public static function openWindows(): Collection {
        try {
            /** @var Collection<int, self> $windows */
            $windows = Cache::remember(self::CACHE_KEY, 60, static function (): Collection {
                return self::query()
                    ->whereIn('status', self::OPEN_STATUSES)
                    ->where('ends_at', '>', now()->subHour())
                    ->orderBy('starts_at')
                    ->get();
            });

            return $windows;
        } catch (\Throwable) {
            return new Collection;
        }
    }

    /** Wirksames Fenster für System bzw. eine Organisation. */
    public static function effectiveFor(?int $organizationId): ?self {
        return self::openWindows()->first(function (self $window) use ($organizationId): bool {
            if (!$window->isEffectiveNow()) {
                return false;
            }
            if ($window->scope === self::SCOPE_SYSTEM) {
                return true;
            }

            return $organizationId !== null && (int) $window->organization_id === $organizationId;
        });
    }

    /** Angekündigtes, bevorstehendes Fenster (Banner-Vorlauf). */
    public static function upcomingFor(?int $organizationId): ?self {
        return self::openWindows()->first(function (self $window) use ($organizationId): bool {
            if (!$window->isAnnouncedUpcoming()) {
                return false;
            }
            if ($window->scope === self::SCOPE_SYSTEM) {
                return true;
            }

            return $organizationId !== null && (int) $window->organization_id === $organizationId;
        });
    }

    public static function systemActiveNow(): bool {
        return self::openWindows()->contains(
            fn(self $window): bool => $window->scope === self::SCOPE_SYSTEM && $window->isEffectiveNow(),
        );
    }
}
