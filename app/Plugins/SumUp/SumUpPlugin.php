<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SumUpPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SumUp;

use App\Models\Platform\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{OnlinePaymentProvider, PluginCapability, SettingsField};
use App\Plugins\SumUp\Api\SumUpCheckoutClient;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Online-Zahlung über SumUp Hosted Checkout (MVP-1070): je Zahlungslink-Aufruf
 * ein Checkout über den offenen Betrag, 30 Minuten gültig. SumUp meldet
 * Statuswechsel an die Webhook-Adresse; der Stand kommt aus dem Checkout.
 */
class SumUpPlugin extends AbstractPlugin implements OnlinePaymentProvider {
    public const ID = 'sumup';

    public const SERVICE_PROVIDER = SumUpServiceProvider::class;

    public function name(): string {
        return 'SumUp';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Online-Zahlung von Rechnungen über den gehosteten Checkout von SumUp (Karte, Apple Pay, Google Pay).');
    }

    public function capabilities(): array {
        return [PluginCapability::OnlinePayment];
    }

    public function onlinePaymentProviderId(): string {
        return self::ID;
    }

    public function createCheckout(Organization $organization, OnlinePaymentRequest $request): OnlinePaymentCheckout {
        return $this->client($organization)->createCheckout($request);
    }

    public function fetchPayment(Organization $organization, string $providerReference): OnlinePaymentSnapshot {
        return $this->client($organization)->fetchCheckout($providerReference);
    }

    public function webhookReference(Request $request): ?string {
        if ($request->input('event_type') !== 'CHECKOUT_STATUS_CHANGED') {
            return null;
        }
        $id = $request->input('id');

        return is_string($id) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) === 1 ? $id : null;
    }

    public function settingsSchema(): array {
        return [
            SettingsField::password('api_key', (string) __('API-Schlüssel'), required: true,
                help: (string) __('Aus dem SumUp-Konto unter „Entwickler“ → „API-Schlüssel“ (sup_sk_…).'))->toArray(),
            SettingsField::text('merchant_code', (string) __('Händlercode'), required: true,
                help: (string) __('Steht im SumUp-Konto unter „Profil“ (beginnt meist mit „M“).'))->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! SumUpConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('API-Schlüssel oder Händlercode fehlt.'), code: 'not_configured');
        }

        return PluginHealth::pingHealth(
            ping: fn (): bool => $this->client($organization)->checkCredentials(),
            unreachableMessage: (string) __('SumUp lehnt den API-Schlüssel ab.'),
            okMessage: (string) __('Verbunden — SumUp erreichbar.'),
            errorStatus: PluginHealth::STATUS_FAILING,
        );
    }

    private function client(Organization $organization): SumUpCheckoutClient {
        $config = SumUpConfig::resolve((int) $organization->id);
        if (! SumUpConfig::isConfigured((int) $organization->id)) {
            throw new RuntimeException('SumUp ist nicht eingerichtet.');
        }

        return new SumUpCheckoutClient($config);
    }
}
