<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Globaler Zustand eines Plugins (Installation, Health, Auto-Disable).
 *
 * @property int $id
 * @property string $plugin_id
 * @property int|null $organization_id
 * @property string|null $installed_version
 * @property Carbon|null $installed_at
 * @property Carbon|null $last_health_check_at
 * @property string|null $last_health_status
 * @property string|null $last_health_message
 * @property int|null $last_health_latency_ms
 * @property string|null $last_health_code
 * @property string|null $last_announced_status
 * @property int $health_streak
 * @property Carbon|null $last_ok_at
 * @property int $failure_count
 * @property Carbon|null $failure_window_started_at
 * @property string|null $disabled_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PluginState extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'plugin_id',
        'organization_id',
        'installed_version',
        'installed_at',
        'last_health_check_at',
        'last_health_status',
        'last_health_message',
        'last_health_latency_ms',
        'last_health_code',
        'last_announced_status',
        'health_streak',
        'last_ok_at',
        'failure_count',
        'failure_window_started_at',
        'disabled_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'organization_id' => 'integer',
        'installed_at' => 'datetime',
        'last_health_check_at' => 'datetime',
        'last_ok_at' => 'datetime',
        'failure_count' => 'integer',
        'failure_window_started_at' => 'datetime',
        'last_health_latency_ms' => 'integer',
        'health_streak' => 'integer',
    ];

    /**
     * Zustand je (Plugin, Organisation). `organization_id = null` ist der
     * globale Zustand (globale Plugins bzw. Schema-/Boot-Ebene); per-Org-Plugins
     * führen je Organisation eine eigene Zeile.
     */
    public static function findOrInit(string $pluginId, ?int $organizationId = null): self {
        return static::query()
            ->firstOrNew(['plugin_id' => $pluginId, 'organization_id' => $organizationId]);
    }

    protected static function booted(): void {
        // Sicherheitsnetz zur Manager-Memoisierung (Review 2026-08, W2e):
        // jeder Zustands-Write invalidiert die enabled()/autoDisabled-Sicht —
        // auch Schreibpfade außerhalb des PluginErrorRecorder bleiben frisch.
        $flush = static function (): void {
            try {
                if (app()->resolved(\App\Plugins\PluginManager::class)) {
                    app(\App\Plugins\PluginManager::class)->flushRuntimeCaches();
                }
            } catch (\Throwable) {
                // Cache-Invalidierung darf nie werfen.
            }
        };
        static::saved($flush);
        static::deleted($flush);
    }

    public function isAutoDisabled(): bool {
        return $this->disabled_reason !== null && $this->disabled_reason !== '';
    }

    /**
     * Zustands-Map (plugin_id => Zeile) für eine Organisation: org-spezifische
     * Zeile gewinnt über die globale (organization_id = null). Für Übersichten
     * (Admin/CLI), die je Plugin genau einen Zustand anzeigen.
     *
     * @return \Illuminate\Support\Collection<string, static>
     */
    public static function mapForOrganization(?int $organizationId): \Illuminate\Support\Collection {
        return static::query()
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id');
                if ($organizationId !== null) {
                    $q->orWhere('organization_id', $organizationId);
                }
            })
            // global zuerst, org-spezifisch zuletzt → keyBy behält die org-spezifische.
            ->orderByRaw('organization_id is null desc')
            ->get()
            ->keyBy('plugin_id');
    }

    /**
     * Einzelner, kontextbezogener Zustand: org-spezifisch bevorzugt, sonst global.
     */
    public static function forContext(string $pluginId, ?int $organizationId): ?static {
        return static::query()
            ->where('plugin_id', $pluginId)
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id');
                if ($organizationId !== null) {
                    $q->orWhere('organization_id', $organizationId);
                }
            })
            ->orderByRaw('organization_id is null asc') // org-spezifisch zuerst
            ->first();
    }
}
