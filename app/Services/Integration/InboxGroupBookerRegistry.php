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

/**
 * Bildet eine plugin_id auf ihren {@see InboxGroupBooker} ab (gruppierte
 * Zeit-Import-Auflösung). Plugins tragen ihren Bucher beim Booten ein
 * (MVP-1030), Module über `Manifest::extensions()` (MVP-863).
 */
class InboxGroupBookerRegistry {
    /** @var array<string, class-string<InboxGroupBooker>> Plugin-Kennung → Bucher, von den Plugins eingetragen */
    private array $plugins = [];

    /** @var array<string, class-string<InboxGroupBooker>>|null */
    private ?array $map = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @param  class-string<InboxGroupBooker>  $booker */
    public function register(string $pluginId, string $booker): void {
        $this->plugins[$pluginId] = $booker;
        $this->map = null;
    }

    public function for(string $pluginId): ?InboxGroupBooker {
        $class = $this->map()[$pluginId] ?? null;

        return $class !== null ? app($class) : null;
    }

    /** @return list<string> */
    public function pluginIds(): array {
        return array_keys($this->map());
    }

    /** @return array<string, class-string<InboxGroupBooker>> */
    private function map(): array {
        if ($this->map === null) {
            $this->map = $this->plugins;
            foreach ($this->modules->extensions(InboxGroupBooker::class) as $class) {
                /** @var InboxGroupBooker $booker */
                $booker = app($class);
                $this->map[$booker->pluginId()] = $class;
            }
        }

        return $this->map;
    }
}
