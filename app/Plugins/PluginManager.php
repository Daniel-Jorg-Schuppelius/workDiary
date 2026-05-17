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

use App\Plugins\Contracts\Plugin;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Central registry for all plugin instances. Resolved as a singleton from the
 * container by PluginServiceProvider. Plugins themselves are pulled from the
 * container so they can declare their own dependencies.
 */
class PluginManager
{
    /** @var Collection<string, Plugin> */
    private Collection $plugins;

    public function __construct()
    {
        $this->plugins = collect();
    }

    public function register(Plugin $plugin): void
    {
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
    public function all(): Collection
    {
        return $this->plugins;
    }

    /** @return Collection<string, Plugin> */
    public function enabled(): Collection
    {
        return $this->plugins->filter(fn (Plugin $p): bool => $p->isEnabled());
    }

    public function find(string $id): ?Plugin
    {
        return $this->plugins->get($id);
    }

    /**
     * Plugins that advertise the given capability identifier.
     *
     * @return Collection<string, Plugin>
     */
    public function withCapability(string $capability): Collection
    {
        return $this->enabled()->filter(
            fn (Plugin $p): bool => in_array($capability, $p->capabilities(), true),
        );
    }
}
