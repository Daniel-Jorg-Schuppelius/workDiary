<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacturationCapabilitySource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets;

use App\Enums\Finance\TransferTarget;
use App\Plugins\Support\Contracts\PluginCapabilitySource;
use Throwable;

/**
 * Belegübergabe als Plugin-Fähigkeit: Ziele tragen keine Plugin-Kennung,
 * sondern hängen am gleichnamigen {@see TransferTarget}; ein Ziel ohne
 * Adapter wirft — hier schlicht „kann es nicht".
 */
final class FacturationCapabilitySource implements PluginCapabilitySource {
    public function __construct(private readonly FacturationTargetRegistry $targets) {}

    public function labelFor(string $pluginId): ?string {
        $target = TransferTarget::tryFrom($pluginId);
        if ($target === null) {
            return null;
        }
        try {
            $this->targets->for($target);
        } catch (Throwable) {
            return null;
        }

        return (string) __('plugins.capability.facturation');
    }
}
