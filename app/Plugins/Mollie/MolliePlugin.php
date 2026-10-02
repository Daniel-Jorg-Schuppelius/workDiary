<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MolliePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Mollie;

use App\Models\Platform\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{OnlinePaymentProvider, PluginCapability, SettingsField};
use App\Plugins\Mollie\Api\MolliePaymentClient;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Online-Zahlung über Mollie (MVP-1069): je Zahlungslink-Aufruf eine Zahlung
 * über den offenen Betrag. Den Webhook übergibt workDiary je Zahlung; Mollie
 * meldet auch Erstattungen und Rückbuchungen dorthin.
 */
class MolliePlugin extends AbstractPlugin implements OnlinePaymentProvider {
    public const ID = 'mollie';

    public const SERVICE_PROVIDER = MollieServiceProvider::class;

    public function name(): string {
        return 'Mollie';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Online-Zahlung von Rechnungen über Mollie (Karte, PayPal, SEPA-Überweisung, Klarna und weitere).');
    }

    public function capabilities(): array {
        return [PluginCapability::OnlinePayment];
    }

    public function onlinePaymentProviderId(): string {
        return self::ID;
    }

    public function createCheckout(Organization $organization, OnlinePaymentRequest $request): OnlinePaymentCheckout {
        return $this->client($organization)->createPayment($request);
    }

    public function fetchPayment(Organization $organization, string $providerReference): OnlinePaymentSnapshot {
        return $this->client($organization)->fetchPayment($providerReference);
    }

    public function webhookReference(Request $request): ?string {
        $id = $request->input('id');

        return is_string($id) && preg_match('/^tr_[A-Za-z0-9]+$/', $id) === 1 ? $id : null;
    }

    public function settingsSchema(): array {
        return [
            SettingsField::password('api_key', (string) __('API-Schlüssel'), required: true,
                help: (string) __('Aus dem Mollie-Dashboard unter „Entwickler“ → „API-Schlüssel“ (live_… bzw. test_…).'))->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! MollieConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('Kein API-Schlüssel hinterlegt.'), code: 'not_configured');
        }

        return PluginHealth::pingHealth(
            ping: fn (): bool => $this->client($organization)->checkCredentials(),
            unreachableMessage: (string) __('Mollie lehnt den API-Schlüssel ab.'),
            okMessage: (string) __('Verbunden — Mollie erreichbar.'),
            errorStatus: PluginHealth::STATUS_FAILING,
        );
    }

    private function client(Organization $organization): MolliePaymentClient {
        $config = MollieConfig::resolve((int) $organization->id);
        if ($config['api_key'] === '') {
            throw new RuntimeException('Mollie ist nicht eingerichtet.');
        }

        return new MolliePaymentClient($config);
    }
}
