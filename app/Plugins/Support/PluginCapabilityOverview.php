<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCapabilityOverview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Enums\Finance\TransferTarget;
use App\Plugins\Contracts\Plugin;
use App\Plugins\Support\Mirror\MirrorTargetRegistry;
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Inventory\ExternalInventoryDispatcherResolver;
use Throwable;

/**
 * Was ein Plugin KANN — für die Anzeige, nicht für den Vertrag
 * (Entscheid 2026-08-21 zum Audit-Befund W1.6).
 *
 * Einige Fähigkeiten hängen nicht am `PluginCapability`-Enum, sondern an
 * eigenen Registries: die Belegübergabe an der
 * {@see FacturationTargetRegistry}, die Dateispiegelung an der
 * {@see MirrorTargetRegistry}, der Bestands-Rückschrieb am
 * {@see ExternalInventoryDispatcherResolver}. Für den Vertrag ist das richtig
 * so — ein Enum-Case hätte nur eine dünne Delegation der Plugin-Klasse auf den
 * Registry-Dienst erzwungen.
 *
 * Für die Admin-Übersicht ist es aber falsch: sevDesk, easybill und orgaMAX
 * standen dort ohne jede Fähigkeit, obwohl sie fakturieren. Diese Klasse fügt
 * die Registry-Fähigkeiten nur für die Anzeige hinzu — kein Interface-Zwang,
 * keine Fassade.
 */
class PluginCapabilityOverview {
    public function __construct(
        private readonly FacturationTargetRegistry $facturation,
        private readonly MirrorTargetRegistry $mirrors,
        private readonly ExternalInventoryDispatcherResolver $inventory,
    ) {}

    /**
     * Anzeige-Labels eines Plugins: erklärte Fähigkeiten zuerst, danach die
     * aus den Registries abgeleiteten.
     *
     * @return list<string>
     */
    public function labelsFor(Plugin $plugin): array {
        $labels = array_map(
            static fn ($capability): string => (string) $capability->label(),
            $plugin->capabilities(),
        );

        foreach ($this->registryLabels($plugin->id()) as $label) {
            if (! in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        return array_values($labels);
    }

    /**
     * Fähigkeiten, die über eigene Registries laufen.
     *
     * @return list<string>
     */
    public function registryLabels(string $pluginId): array {
        $labels = [];

        if ($this->isFacturationTarget($pluginId)) {
            $labels[] = (string) __('plugins.capability.facturation');
        }
        if ($this->mirrors->get($pluginId) !== null) {
            $labels[] = (string) __('plugins.capability.mirror');
        }
        if ($this->inventory->for($pluginId) instanceof ExternalInventoryDispatcher) {
            $labels[] = (string) __('plugins.capability.inventory');
        }

        return $labels;
    }

    /**
     * Die Übergabeziele tragen keine Plugin-Kennung; sie sind über den
     * gleichnamigen {@see TransferTarget}-Fall gebunden. Ein Ziel ohne Adapter
     * wirft — das ist hier kein Fehler, sondern schlicht „kann es nicht".
     */
    private function isFacturationTarget(string $pluginId): bool {
        $target = TransferTarget::tryFrom($pluginId);
        if ($target === null) {
            return false;
        }

        try {
            $this->facturation->for($target);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
