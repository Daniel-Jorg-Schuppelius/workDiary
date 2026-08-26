<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsProviderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Models\Organization;
use App\Plugins\Contracts\{PluginCapability, SmsProvider};
use App\Plugins\PluginManager;
use App\Support\OrganizationContext;

/**
 * Welches SMS-Gateway gilt für diese Organisation (Feature 147, MVP-730)?
 *
 * Der Kanal ist anbieterneutral: aktiviert wird ein Gateway-Plugin
 * ({@see PluginCapability::SmsGateway}) mit seinen eigenen, verschlüsselten
 * Zugangsdaten in `plugin_settings`. Sind versehentlich mehrere aktiv, gewinnt
 * das erste in stabiler Reihenfolge — es wird nichts gefächert: eine Alarm-SMS
 * soll einmal ankommen, nicht bei jedem Anbieter einmal.
 *
 * Der Org-Kontext wird hier gebunden, weil `PluginManager::enabled()` ihn
 * auswertet — Scheduler und Queue laufen ohne gebundene Organisation.
 */
class SmsProviderResolver {
    public function __construct(private readonly PluginManager $plugins) {}

    public function forOrganization(Organization $organization): ?SmsProvider {
        return OrganizationContext::run($organization, function (): ?SmsProvider {
            foreach ($this->plugins->withCapability(PluginCapability::SmsGateway) as $plugin) {
                if ($plugin instanceof SmsProvider) {
                    return $plugin;
                }
            }

            return null;
        });
    }

    public function hasGateway(Organization $organization): bool {
        return $this->forOrganization($organization) !== null;
    }
}
