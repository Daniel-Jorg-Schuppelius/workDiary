<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSettingsMemo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\Platform\PluginSetting;

/**
 * Plugin-Einstellungen einer Organisation je Request bzw. Job in einer
 * Abfrage (scoped gebunden). Ohne Memo fragte die Aktivliste jede Seite
 * einmal je Plugin; `PluginSetting` entwertet beim Speichern und Löschen.
 */
final class PluginSettingsMemo {
    /** @var array<int, array<string, PluginSetting>> */
    private array $rows = [];

    public function row(int $organizationId, string $pluginId): ?PluginSetting {
        $this->rows[$organizationId] ??= PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->get()
            ->keyBy('plugin_id')
            ->all();

        return $this->rows[$organizationId][$pluginId] ?? null;
    }

    public function flush(): void {
        $this->rows = [];
    }
}
