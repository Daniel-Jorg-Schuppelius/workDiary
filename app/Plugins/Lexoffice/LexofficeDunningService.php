<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDunningService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{ExternalReference, LexofficeVoucher};
use App\Plugins\Support\PluginHttp;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Mahnung-Anbindung an Lexoffice (Feature 045).
 *
 * Quelle ist eine ÜBERFÄLLIGE (bereits synchronisierte) Lexoffice-Rechnung
 * ({@see LexofficeVoucher} vom Typ Rechnung). Eine Mahnung entsteht in Lexoffice
 * als Folgebeleg einer Vorgänger-Rechnung: `POST /v1/dunnings?precedingSalesVoucherId={id}`.
 * Der Body wird aus der geladenen Rechnung abgeleitet (Adresse/Positionen).
 *
 * HTTP über {@see PluginHttp} (Laravel-HTTP-Client, Http::fake()-testbar).
 */
class LexofficeDunningService {
    public const EXT_TYPE_DUNNING = 'dunning';

    /** Lexoffice-voucherType-Werte für Verkaufsrechnungen. */
    private const INVOICE_TYPES = ['invoice', 'salesinvoice'];

    /**
     * Erstellt eine Lexoffice-Mahnung zur überfälligen Rechnung.
     *
     * @throws RuntimeException Bei fehlender Konfiguration, falschem Belegtyp
     *                          oder API-Fehler.
     */
    public function push(LexofficeVoucher $voucher): ExternalReference {
        $config = LexofficeConfig::resolve($voucher->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
        }

        if (! in_array((string) $voucher->voucher_type, self::INVOICE_TYPES, true)) {
            throw new RuntimeException((string) __('finance.error.lexoffice_dunning_not_invoice'));
        }

        $precedingId = (string) $voucher->external_id;

        // Vorgänger-Rechnung laden (Adresse/Positionen für den Mahnungs-Body).
        $invoiceResponse = $this->http($config)->get($config['base_url'] . '/invoices/' . $precedingId);
        if (! $invoiceResponse->successful()) {
            throw new RuntimeException(sprintf('Lexoffice invoice fetch failed: HTTP %d', $invoiceResponse->status()));
        }
        $invoice = (array) ($invoiceResponse->json() ?? []);

        $payload = [
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'address' => $invoice['address'] ?? [],
            'lineItems' => $invoice['lineItems'] ?? [],
            'totalPrice' => ['currency' => (string) data_get($invoice, 'totalPrice.currency', 'EUR')],
            'taxConditions' => $invoice['taxConditions'] ?? ['taxType' => 'net'],
            'title' => (string) __('Mahnung'),
        ];

        $response = $this->http($config)
            ->post($config['base_url'] . '/dunnings?precedingSalesVoucherId=' . $precedingId, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Lexoffice dunning failed: HTTP %d %s',
                $response->status(),
                mb_substr((string) $response->body(), 0, 500),
            ));
        }

        $body = (array) ($response->json() ?? []);
        $externalId = (string) ($body['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('Lexoffice dunning returned no id.');
        }

        return ExternalReference::create([
            'organization_id' => $voucher->organization_id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => self::EXT_TYPE_DUNNING,
            'referenceable_type' => $voucher->getMorphClass(),
            'referenceable_id' => $voucher->getKey(),
            'external_id' => $externalId,
            'payload' => ['lexoffice_id' => $externalId, 'preceding_invoice_id' => $precedingId] + $body,
            'synced_at' => now(),
        ]);
    }

    /**
     * Die ExternalReference der Mahnung zu einer Rechnung.
     */
    public function reference(LexofficeVoucher $voucher): ?ExternalReference {
        return ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', self::EXT_TYPE_DUNNING)
            ->where('referenceable_type', $voucher->getMorphClass())
            ->where('referenceable_id', $voucher->getKey())
            ->first();
    }

    /** @param  array{api_key: ?string, base_url: string}  $config */
    private function http(array $config): PendingRequest {
        return PluginHttp::for('lexoffice')
            ->withToken((string) $config['api_key'])
            ->acceptJson()
            ->asJson();
    }
}
