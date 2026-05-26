<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSetting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Settings eines Plugins pro Organisation. Das `settings`-JSON wird symmetrisch
 * mit APP_KEY verschlüsselt in der DB abgelegt — DB-Dumps enthalten den
 * API-Key also nicht im Klartext.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $plugin_id
 * @property bool $enabled
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PluginSetting extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'enabled',
        'settings',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'encrypted:array',
    ];

    /**
     * Liest einen einzelnen Setting-Wert mit Default-Fallback.
     */
    public function get(string $key, mixed $default = null): mixed {
        $settings = $this->settings ?? [];

        return $settings[$key] ?? $default;
    }

    /**
     * Setter, der die JSON-Struktur einfach erweitert ohne den Caller
     * zu zwingen, das Array selbst zu mergen.
     */
    public function set(string $key, mixed $value): void {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    /**
     * Sucht die Settings eines Plugins für eine Organisation, mit
     * stillem Fallback auf ein leeres, nicht persistiertes Objekt.
     */
    public static function forOrganization(int $organizationId, string $pluginId): self {
        return static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->first() ?? new self([
                'organization_id' => $organizationId,
                'plugin_id' => $pluginId,
                'enabled' => false,
                'settings' => [],
            ]);
    }
}
