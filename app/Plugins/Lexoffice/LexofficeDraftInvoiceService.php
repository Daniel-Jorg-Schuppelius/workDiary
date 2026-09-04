<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDraftInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Rechnungsentwurf in Lexoffice anlegen (Feature 152, MVP-764) — ohne lokale
 * Rechnung, ohne Festschreiben: `POST /invoices` ohne `finalize`. Der
 * Betreiber prüft und schließt den Entwurf in Lexoffice ab; der stündliche
 * Belegspiegel holt die fertige Rechnung, der Vorschlagslauf ordnet sie zu.
 *
 * @phpstan-type DraftLine array{name: string, description: string, quantity: float, unit_name: string, unit_net: float}
 */
final class LexofficeDraftInvoiceService {
    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
        private readonly ?float $requestInterval = null,
    ) {}

    /**
     * @param  list<DraftLine>  $lines
     * @return string  Lexoffice-ID des Entwurfs
     */
    public function createDraft(string $contactId, array $lines, string $title, string $introduction, string $remark, float $taxRate, string $currency = 'EUR', string $taxType = 'net', ?CarbonImmutable $date = null): string {
        if ($lines === []) {
            throw new RuntimeException('Rechnungsentwurf ohne Positionen.');
        }
        $date ??= CarbonImmutable::today();
        $payload = array_filter([
            'voucherDate' => $date->format('Y-m-d') . 'T00:00:00.000+01:00',
            'address' => ['contactId' => $contactId],
            'lineItems' => array_map(static fn(array $line): array => array_filter([
                'type' => 'custom',
                'name' => mb_substr($line['name'], 0, 255),
                'description' => $line['description'] !== '' ? mb_substr($line['description'], 0, 2000) : null,
                'quantity' => round($line['quantity'], 2),
                'unitName' => $line['unit_name'],
                'unitPrice' => ['currency' => $currency, 'netAmount' => round($line['unit_net'], 2), 'taxRatePercentage' => $taxRate],
            ], static fn($v) => $v !== null), $lines),
            'totalPrice' => ['currency' => $currency],
            'taxConditions' => ['taxType' => $taxType],
            'shippingConditions' => ['shippingDate' => $date->format('Y-m-d') . 'T00:00:00.000+01:00', 'shippingType' => 'service'],
            'title' => $title,
            'introduction' => $introduction !== '' ? $introduction : null,
            'remark' => $remark !== '' ? $remark : null,
        ], static fn($v) => $v !== null && $v !== '');

        $response = $this->api()->postJson($this->baseUrl . '/invoices', $payload);
        if (! $response->successful()) {
            throw LexofficeApiException::fromResponse($response, 'Rechnungsentwurf', 'Rechnungen anlegen');
        }
        $id = (string) ($response->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Lexoffice hat keine ID für den Rechnungsentwurf geliefert.');
        }

        return $id;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $this->baseUrl, $this->requestInterval ?? LexofficeConfig::requestInterval());
            $this->api->setAuthentication(new BearerAuthentication($this->apiKey));
        }

        return $this->api;
    }
}
