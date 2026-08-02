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
use App\Plugins\Contracts\{Plugin, PluginCapability, PluginCapabilityContract, SlotRenderer};
use App\Plugins\Support\PluginOrgContext;
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
 *
 * Härtung (Review 2026-08, W2e/W3e): `enabled()` ist pro Org-Kontext
 * memoisiert (die frühere Variante feuerte ~40 Queries pro Navigationsaufbau),
 * ein werfendes `isEnabled()` gilt als deaktiviert statt die Seite zu reißen,
 * und inkompatible Plugins (Kernversion außerhalb min/max) werden bereits hier
 * gefiltert — nicht erst beim nächsten stündlichen Healthcheck.
 */
class PluginManager {
    /** @var Collection<string, Plugin> */
    private Collection $plugins;

    /** @var array<int, list<string>> Aktivierte Plugin-IDs je Org-Kontext (0 = global). */
    private array $enabledIdCache = [];

    /** @var array<int, array<int, string>> Letzter bekannter Auto-Disable-Stand je Org-Kontext. */
    private array $autoDisabledCache = [];

    /** @var array<string, bool> Kompatibilität je Plugin-ID (reine Versionsrechnung). */
    private array $compatibilityCache = [];

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
        $this->flushRuntimeCaches();
    }

    /** @return Collection<string, Plugin> */
    public function all(): Collection {
        return $this->plugins;
    }

    /** @return Collection<string, Plugin> */
    public function enabled(): Collection {
        $key = PluginOrgContext::currentId() ?? 0;
        $ids = $this->enabledIdCache[$key] ??= $this->computeEnabledIds();
        $lookup = array_fill_keys($ids, true);

        return $this->plugins->filter(fn(Plugin $p): bool => isset($lookup[$p->id()]));
    }

    /**
     * Invalide die memoisierten Sichten — nach Toggle/Settings-Änderungen,
     * Auto-Disable oder Reset (Aufrufer: Admin-Controller, ErrorRecorder).
     */
    public function flushRuntimeCaches(): void {
        $this->enabledIdCache = [];
        $this->autoDisabledCache = [];
    }

    /** @return list<string> */
    private function computeEnabledIds(): array {
        $disabled = $this->autoDisabledIds();

        $ids = [];
        foreach ($this->plugins as $plugin) {
            if (in_array($plugin->id(), $disabled, true)) {
                continue;
            }
            if (! $this->isCompatible($plugin)) {
                continue;
            }
            try {
                $enabled = $plugin->isEnabled();
            } catch (\Throwable $e) {
                // Ein werfendes isEnabled() darf keine Seite reißen (D11):
                // Plugin gilt als deaktiviert, der Fehler landet in der Inbox.
                $enabled = false;
                try {
                    app(PluginErrorRecorder::class)->record($plugin->id(), 'runtime', $e, [], PluginOrgContext::currentId());
                } catch (\Throwable) {
                    // Aufzeichnung darf selbst nie werfen.
                }
            }
            if ($enabled) {
                $ids[] = $plugin->id();
            }
        }

        return $ids;
    }

    /** Kompatibilität wird beim Laden durchgesetzt (W3e) — memoisiert, reine Versionsrechnung. */
    private function isCompatible(Plugin $plugin): bool {
        return $this->compatibilityCache[$plugin->id()] ??= PluginCompatibility::for($plugin)->compatible;
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
            if (! $plugin instanceof SlotRenderer) {
                continue;
            }
            // Exception-isoliert: ein fehlerhaftes Plugin darf die Seite nicht
            // zerreißen — der Fehler wird als runtime-Fehler aufgezeichnet.
            $html = $this->invoke($plugin, fn(): ?string => $plugin->renderActions($slot, $context));
            if (is_string($html) && $html !== '') {
                $out .= $html;
            }
        }

        return $out;
    }

    /**
     * Führt einen Plugin-Aufruf gekapselt aus: fängt jede Exception, zeichnet sie
     * org-bezogen als Plugin-Fehler (Phase $phase) auf und gibt null zurück, statt
     * die App zu reißen. Zentrale Stelle für Laufzeit-Robustheit.
     */
    public function invoke(Plugin|string $plugin, callable $fn, string $phase = 'runtime'): mixed {
        $id = $plugin instanceof Plugin ? $plugin->id() : $plugin;
        try {
            return $fn();
        } catch (\Throwable $e) {
            try {
                $orgId = PluginOrgContext::currentId();
                app(PluginErrorRecorder::class)->record($id, $phase, $e, [], $orgId);
            } catch (\Throwable) {
                // Aufzeichnung darf selbst nie werfen.
            }

            return null;
        }
    }

    /**
     * Plugins, die das gegebene Contract-Interface implementieren (typsicher).
     *
     * @param  class-string  $interface
     * @return Collection<string, Plugin>
     */
    public function implementing(string $interface): Collection {
        return $this->enabled()->filter(fn(Plugin $p): bool => $p instanceof $interface);
    }

    /**
     * Plugins that advertise the given capability identifier. Vergleich über
     * {@see PluginCapabilityContract::identifier()} — damit funktionieren auch
     * Registry-Capabilities außerhalb des Kern-Enums (W5e).
     *
     * @return Collection<string, Plugin>
     */
    public function withCapability(PluginCapabilityContract|PluginCapability $capability): Collection {
        $identifier = $capability->identifier();

        return $this->enabled()->filter(
            fn(Plugin $p): bool => collect($p->capabilities())
                ->contains(fn(PluginCapabilityContract $c): bool => $c->identifier() === $identifier),
        );
    }

    /**
     * IDs der per Auto-Disable stillgelegten Plugins für den aktuellen Kontext.
     * Eine globale Stilllegung (organization_id = null, z. B. Boot-/Schema-Fehler)
     * gilt überall; eine per-Org-Stilllegung nur, wenn ihre Organisation aktuell
     * gebunden ist. Defensiv abgefragt — bei DB-Ausfall gilt der letzte bekannte
     * Stand dieses Requests (statt still „alles aktiv", C6-Milderung).
     *
     * @return array<int, string>
     */
    private function autoDisabledIds(): array {
        $key = PluginOrgContext::currentId() ?? 0;
        try {
            $orgId = PluginOrgContext::currentId();

            return $this->autoDisabledCache[$key] = PluginState::query()
                ->whereNotNull('disabled_reason')
                ->where(function ($q) use ($orgId): void {
                    $q->whereNull('organization_id');
                    if ($orgId !== null) {
                        $q->orWhere('organization_id', $orgId);
                    }
                })
                ->pluck('plugin_id')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return $this->autoDisabledCache[$key] ?? [];
        }
    }
}
