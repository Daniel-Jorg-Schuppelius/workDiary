<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentProviderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\OnlinePayment;

use App\Models\Platform\Organization;
use App\Plugins\Contracts\{OnlinePaymentProvider, PluginCapability};
use App\Plugins\PluginManager;
use App\Settings\SettingsRegistry;
use App\Support\OrganizationContext;

/**
 * Welcher Zahlungsanbieter gilt für die Organisation (MVP-1067)? Aktiviert wird
 * ein Plugin mit {@see PluginCapability::OnlinePayment} und eigenen
 * Zugangsdaten; sind mehrere aktiv, entscheidet `payments.online.provider`,
 * sonst das erste in stabiler Reihenfolge.
 */
class OnlinePaymentProviderResolver {
    public function __construct(
        private readonly PluginManager $plugins,
        private readonly SettingsRegistry $settings,
    ) {}

    public function forOrganization(Organization $organization): ?OnlinePaymentProvider {
        return OrganizationContext::run($organization, function () use ($organization): ?OnlinePaymentProvider {
            $providers = $this->plugins->withCapability(PluginCapability::OnlinePayment)
                ->filter(static fn ($plugin): bool => $plugin instanceof OnlinePaymentProvider)
                ->values();
            $chosen = (string) $this->settings->effective('payments.online.provider', $organization)->value;

            return $providers->first(static fn (OnlinePaymentProvider $provider): bool => $provider->onlinePaymentProviderId() === $chosen)
                ?? $providers->first();
        });
    }

    /** Anbieter zur Kennung — für Webhooks, bevor die Organisation feststeht. */
    public function byId(string $providerId): ?OnlinePaymentProvider {
        $plugin = $this->plugins->get($providerId);

        return $plugin instanceof OnlinePaymentProvider && $plugin->onlinePaymentProviderId() === $providerId ? $plugin : null;
    }
}
