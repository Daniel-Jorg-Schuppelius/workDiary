<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InboxGroupBookerRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Plugins\OpenProject\OpenProjectGroupBooker;
use App\Plugins\Toggl\TogglGroupBooker;

/**
 * Bildet eine plugin_id auf ihren {@see InboxGroupBooker} ab (gruppierte
 * Zeit-Import-Auflösung). Weitere Plugins (OpenProject, RemoteSupport) werden
 * hier eingetragen.
 */
class InboxGroupBookerRegistry {
    /** @var array<string, class-string<InboxGroupBooker>> */
    private array $map = [
        'toggl' => TogglGroupBooker::class,
        'openproject' => OpenProjectGroupBooker::class,
    ];

    public function for(string $pluginId): ?InboxGroupBooker {
        $class = $this->map[$pluginId] ?? null;

        return $class !== null ? app($class) : null;
    }

    /**
     * @return list<string>
     */
    public function pluginIds(): array {
        return array_keys($this->map);
    }
}
