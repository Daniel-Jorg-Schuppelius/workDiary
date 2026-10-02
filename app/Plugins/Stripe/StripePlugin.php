<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StripePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Stripe;

use App\Models\Platform\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{OnlinePaymentProvider, PluginCapability, SettingsField};
use App\Plugins\Stripe\Api\StripeCheckoutClient;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Online-Zahlung über Stripe Checkout (MVP-1068): je Zahlungslink-Aufruf eine
 * Checkout Session über den offenen Betrag; Stand, Gebühr und Erstattung aus
 * der Session samt Charge und Saldobuchung.
 */
class StripePlugin extends AbstractPlugin implements OnlinePaymentProvider {
    public const ID = 'stripe';

    public const SERVICE_PROVIDER = StripeServiceProvider::class;

    public function name(): string {
        return 'Stripe';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Online-Zahlung von Rechnungen über Stripe Checkout (Karte, Apple Pay, Google Pay, SEPA und weitere).');
    }

    public function capabilities(): array {
        return [PluginCapability::OnlinePayment];
    }

    public function onlinePaymentProviderId(): string {
        return self::ID;
    }

    public function createCheckout(Organization $organization, OnlinePaymentRequest $request): OnlinePaymentCheckout {
        return $this->client($organization)->createSession($request);
    }

    public function fetchPayment(Organization $organization, string $providerReference): OnlinePaymentSnapshot {
        return $this->client($organization)->fetchSession($providerReference);
    }

    public function webhookReference(Request $request): ?string {
        // Nur Session-Ereignisse tragen die Referenz; Erstattungen holt der tägliche Abgleich.
        $object = (array) $request->input('data.object', []);
        if (($object['object'] ?? null) !== 'checkout.session') {
            return null;
        }
        $id = $object['id'] ?? null;

        return is_string($id) && str_starts_with($id, 'cs_') ? $id : null;
    }

    public function settingsSchema(): array {
        return [
            SettingsField::password('secret_key', (string) __('Geheimer Schlüssel'), required: true,
                help: (string) __('Aus dem Stripe-Dashboard unter „Entwickler“ → „API-Schlüssel“ (sk_live_… bzw. sk_test_…). Als Webhook-Endpunkt mit den Ereignissen „checkout.session.*“ eintragen: :url', ['url' => route('payments.webhook', self::ID)]))->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! StripeConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('Kein geheimer Schlüssel hinterlegt.'), code: 'not_configured');
        }

        return PluginHealth::pingHealth(
            ping: fn (): bool => $this->client($organization)->checkCredentials(),
            unreachableMessage: (string) __('Stripe lehnt den Schlüssel ab.'),
            okMessage: (string) __('Verbunden — Stripe erreichbar.'),
            errorStatus: PluginHealth::STATUS_FAILING,
        );
    }

    private function client(Organization $organization): StripeCheckoutClient {
        $config = StripeConfig::resolve((int) $organization->id);
        if ((string) $config['secret_key'] === '') {
            throw new RuntimeException('Stripe ist nicht eingerichtet.');
        }

        return new StripeCheckoutClient($config);
    }
}
