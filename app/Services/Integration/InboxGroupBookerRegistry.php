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

use App\Modules\ModuleRegistry;
use App\Plugins\Clockify\ClockifyGroupBooker;
use App\Plugins\Fritzbox\FritzboxGroupBooker;
use App\Plugins\Kimai\KimaiGroupBooker;
use App\Plugins\OpenProject\OpenProjectGroupBooker;
use App\Plugins\RemoteSupport\RemoteSupportGroupBooker;
use App\Plugins\Toggl\TogglGroupBooker;

/**
 * Bildet eine plugin_id auf ihren {@see InboxGroupBooker} ab (gruppierte
 * Zeit-Import-Auflösung). Weitere Plugins (OpenProject, RemoteSupport) werden
 * hier eingetragen.
 */
class InboxGroupBookerRegistry {
    /** @var array<string, class-string<InboxGroupBooker>> Plugin-Kennung → Bucher der Zeit-/Telefonie-Plugins */
    private const PLUGIN_BOOKERS = [
        'toggl' => TogglGroupBooker::class,
        'kimai' => KimaiGroupBooker::class,
        'clockify' => ClockifyGroupBooker::class,
        'openproject' => OpenProjectGroupBooker::class,
        'remote-support' => RemoteSupportGroupBooker::class,
        'fritzbox' => FritzboxGroupBooker::class,
    ];

    /** @var array<string, class-string<InboxGroupBooker>>|null */
    private ?array $map = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    public function for(string $pluginId): ?InboxGroupBooker {
        $class = $this->map()[$pluginId] ?? null;

        return $class !== null ? app($class) : null;
    }

    /** @return list<string> */
    public function pluginIds(): array {
        return array_keys($this->map());
    }

    /** @return array<string, class-string<InboxGroupBooker>> Plugins fest, Module über `Manifest::extensions()` (MVP-863) */
    private function map(): array {
        if ($this->map === null) {
            $this->map = self::PLUGIN_BOOKERS;
            foreach ($this->modules->extensions(InboxGroupBooker::class) as $class) {
                /** @var InboxGroupBooker $booker */
                $booker = app($class);
                $this->map[$booker->pluginId()] = $class;
            }
        }

        return $this->map;
    }
}
