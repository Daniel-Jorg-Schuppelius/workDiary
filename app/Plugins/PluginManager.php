<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Models\PluginState;
use App\Plugins\Contracts\Plugin;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Central registry for all plugin instances. Resolved as a singleton from the
 * container by PluginServiceProvider. Plugins themselves are pulled from the
 * container so they can declare their own dependencies.
 *
 * Auto-Disable: Plugins, deren {@see PluginState::$disabled_reason} gesetzt
 * ist (siehe {@see PluginErrorRecorder}), werden in {@see enabled()} / {@see withCapability()}
 * automatisch ausgeblendet. Über {@see all()} bleiben sie sichtbar, damit sie
 * in der Admin-UI als „deaktiviert (Auto-Disable)" angezeigt werden können.
 */
class PluginManager {
    /** @var Collection<string, Plugin> */
    private Collection $plugins;

    public function __construct() {
        $this->plugins = collect();
    }

    public function register(Plugin $plugin): void {
        if ($this->plugins->has($plugin->id())) {
            $existing = $this->plugins->get($plugin->id());
            throw new RuntimeException(sprintf(
                'Plugin id "%s" already registered (existing: %s).',
                $plugin->id(),
                $existing !== null ? $existing::class : 'unknown',
            ));
        }
        $this->plugins->put($plugin->id(), $plugin);
    }

    /** @return Collection<string, Plugin> */
    public function all(): Collection {
        return $this->plugins;
    }

    /** @return Collection<string, Plugin> */
    public function enabled(): Collection {
        $disabled = $this->autoDisabledIds();

        return $this->plugins
            ->reject(fn(Plugin $p): bool => in_array($p->id(), $disabled, true))
            ->filter(fn(Plugin $p): bool => $p->isEnabled());
    }

    public function find(string $id): ?Plugin {
        return $this->plugins->get($id);
    }

    /** Alias für {@see find()} — wird historisch von einigen Controllern genutzt. */
    public function get(string $id): ?Plugin {
        return $this->find($id);
    }

    /**
     * Erlaubt Plugins, in einem definierten View-Slot HTML zu rendern (z. B.
     * Buttons in invoices/show, customers/show). Plugins implementieren dafür
     * eine Methode `renderActions(string $slot, mixed $context): ?string`.
     * Liefert die zusammengefügten Plugin-HTML-Schnipsel (oder leer).
     */
    public function renderSlot(string $slot, mixed $context = null): string {
        $out = '';
        foreach ($this->enabled() as $plugin) {
            if (! method_exists($plugin, 'renderActions')) {
                continue;
            }
            $html = $plugin->renderActions($slot, $context);
            if (is_string($html) && $html !== '') {
                $out .= $html;
            }
        }

        return $out;
    }

    /**
     * Plugins that advertise the given capability identifier.
     *
     * @return Collection<string, Plugin>
     */
    public function withCapability(string $capability): Collection {
        return $this->enabled()->filter(
            fn(Plugin $p): bool => in_array($capability, $p->capabilities(), true),
        );
    }

    /**
     * IDs der per Auto-Disable global stillgelegten Plugins. Wird defensiv
     * abgefragt — wenn die plugin_states-Tabelle noch nicht existiert (z. B.
     * vor der ersten Migration), liefern wir eine leere Liste statt zu werfen.
     *
     * @return array<int, string>
     */
    private function autoDisabledIds(): array {
        try {
            return PluginState::query()
                ->whereNotNull('disabled_reason')
                ->pluck('plugin_id')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
