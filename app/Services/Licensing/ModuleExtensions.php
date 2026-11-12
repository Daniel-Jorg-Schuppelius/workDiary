<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleExtensions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Enums\Modules\ModuleKind;
use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Plugins\Contracts\Plugin;
use App\Plugins\PluginManager;
use App\Support\OrganizationContext;

/**
 * Modul-Gate der Erweiterungspunkte (MVP-1587, Konzept „Modul-Konfiguration“
 * §5): Der Sammler eines Erweiterungspunkts fragt hier, statt jeden Beitrag
 * sein Modul selbst prüfen zu lassen. Ein Beitrag zählt für eine Organisation
 * nur, wenn das meldende Fachmodul für sie aktiv ist; Plugin-Beiträge nur,
 * wenn das Plugin für sie eingeschaltet ist. Plattform- und Kernmodule sind
 * immer aktiv (wie {@see \App\Jobs\Middleware\EnsureModuleActive}).
 *
 * Scoped gebunden: der Merker lebt je Request bzw. Job.
 */
final class ModuleExtensions {
    /** @var array<string, bool> "<Organisation>|<Lizenzcode oder plugin:ID>" → aktiv */
    private array $memo = [];

    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleStatusResolver $status,
        private readonly PluginManager $plugins,
    ) {}

    /**
     * Beiträge eines Erweiterungspunkts, deren Modul für die Organisation aktiv ist.
     * Ohne Organisation bleiben nur Beiträge von Plattform und Kern.
     *
     * @template T of object
     * @param  class-string<T>  $interface
     * @return list<class-string<T>>
     */
    public function activeFor(string $interface, Organization|int|null $organization): array {
        $out = [];
        foreach ($this->modules->extensions($interface) as $class) {
            if ($this->allows($class, $organization)) {
                $out[] = $class;
            }
        }

        return $out;
    }

    /** @param  class-string  $class  Beitragsklasse eines Erweiterungspunkts */
    public function allows(string $class, Organization|int|null $organization): bool {
        $plugin = $this->pluginOf($class);
        if ($plugin !== null) {
            return $this->remember($organization, 'plugin:' . $plugin->id(), fn (Organization $org): bool => $this->pluginEnabled($plugin, $org));
        }

        $manifest = $this->modules->extensionOwner($class);
        $license = $manifest !== null && $manifest->kind() === ModuleKind::Feature ? $manifest->licenseCode() : null;
        if ($license === null) {
            return true;
        }

        return $this->remember($organization, $license, fn (Organization $org): bool => $this->status->isActiveFor($org, $license));
    }

    public function flush(): void {
        $this->memo = [];
    }

    /** @param  callable(Organization): bool  $resolve */
    private function remember(Organization|int|null $organization, string $key, callable $resolve): bool {
        $id = $organization instanceof Organization ? (int) $organization->id : $organization;
        if ($id === null) {
            return false;
        }

        return $this->memo[$id . '|' . $key] ??= (function () use ($organization, $id, $resolve): bool {
            $org = $organization instanceof Organization ? $organization : Organization::query()->withoutGlobalScopes()->find($id);

            return $org instanceof Organization && $resolve($org);
        })();
    }

    /** @param  class-string  $class */
    private function pluginOf(string $class): ?Plugin {
        if (! str_starts_with($class, 'App\\Plugins\\')) {
            return null;
        }
        foreach ($this->plugins->all() as $plugin) {
            $namespace = substr($plugin::class, 0, (int) strrpos($plugin::class, '\\') + 1);
            if (str_starts_with($class, $namespace)) {
                return $plugin;
            }
        }

        return null;
    }

    private function pluginEnabled(Plugin $plugin, Organization $organization): bool {
        // Der Plugin-Schalter liest die gebundene Organisation.
        return OrganizationContext::currentId() === (int) $organization->id
            ? $this->plugins->enabled()->has($plugin->id())
            : OrganizationContext::run($organization, fn (): bool => $this->plugins->enabled()->has($plugin->id()));
    }
}
