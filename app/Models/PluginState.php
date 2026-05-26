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
 * @property string|null $installed_version
 * @property Carbon|null $installed_at
 * @property Carbon|null $last_health_check_at
 * @property string|null $last_health_status
 * @property string|null $last_health_message
 * @property int $failure_count
 * @property string|null $disabled_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PluginState extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'plugin_id',
        'installed_version',
        'installed_at',
        'last_health_check_at',
        'last_health_status',
        'last_health_message',
        'failure_count',
        'disabled_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'installed_at' => 'datetime',
        'last_health_check_at' => 'datetime',
        'failure_count' => 'integer',
    ];

    public static function findOrInit(string $pluginId): self {
        return static::query()
            ->firstOrNew(['plugin_id' => $pluginId]);
    }

    public function isAutoDisabled(): bool {
        return $this->disabled_reason !== null && $this->disabled_reason !== '';
    }
}
