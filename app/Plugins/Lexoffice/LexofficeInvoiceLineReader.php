<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceLineReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Reselling\Contracts\InvoiceLineSource;
use App\Services\Reselling\Marketplace\InvoiceLine;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;
use Throwable;

/**
 * Liest Ausgangsrechnungen eines Lexoffice-Kontakts positionsgenau (Feature 151).
 *
 * voucherlist liefert nur Köpfe; für Rechnungen (`invoice`) wird deshalb je
 * Beleg `GET /invoices/{id}` nachgeladen. Buchungsbelege (`salesinvoice`)
 * haben in Lexoffice keine Positionen und kommen als Kopf-Zeile durch.
 * Entwürfe und stornierte Belege bleiben außen vor. Ratelimit: Anfrageabstand
 * über den Client (LexofficeConfig::requestInterval), bei 429 Retry-After.
 */
final class LexofficeInvoiceLineReader implements InvoiceLineSource {
    private const PAGE_SIZE = 250;

    private const EXCLUDED_STATUS = ['draft', 'voided'];

    private ?PluginApiClient $api = null;

    private float $requestInterval;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        ?float $requestInterval = null,
    ) {
        $this->requestInterval = $requestInterval ?? LexofficeConfig::requestInterval();
    }

    /** Tests: weder Anfrageabstand noch Retry-Wartezeit real schlafen. */
    public function withoutThrottle(): self {
        $this->requestInterval = 0.0;
        $this->api = null;

        return $this;
    }

    public function verifyAccess(): void {
        $this->getJson('/profile', [], 'Zugang prüfen');
    }

    public function linesForContact(string $externalContactId, CarbonImmutable $from, CarbonImmutable $to): array {
        $lines = [];
        $page = 0;

        do {
            $body = $this->getJson('/voucherlist', [
                'voucherType' => 'invoice',
                'voucherStatus' => 'any',
                'contactId' => $externalContactId,
                'voucherDateFrom' => $from->format('Y-m-d'),
                'voucherDateTo' => $to->format('Y-m-d'),
                'page' => $page,
                'size' => self::PAGE_SIZE,
            ], 'Belegliste abrufen');

            foreach ((array) ($body['content'] ?? []) as $item) {
                if (! is_array($item) || empty($item['id'])) {
                    continue;
                }
                if (in_array((string) ($item['voucherStatus'] ?? ''), self::EXCLUDED_STATUS, true)) {
                    continue;
                }
                $date = $this->date($item['voucherDate'] ?? null);
                if ($date === null) {
                    continue;
                }

                // Nur Lexoffice-eigene Rechnungen (RE/…) tragen Positionen. Buchungsbelege
                // (`salesinvoice`) sind in anderen Programmen erstellte Fremdrechnungen —
                // beim Reseller die Domainrechnungen (10021-01-2020) — und decken nie eine Lizenz.
                if ((string) ($item['voucherType'] ?? '') !== 'invoice') {
                    continue;
                }
                array_push($lines, ...$this->invoiceLines((string) $item['id'], (string) ($item['voucherNumber'] ?? ''), $date, $externalContactId));
            }

            $totalPages = max(1, (int) ($body['totalPages'] ?? 1));
            $page++;
        } while ($page < $totalPages);

        return $lines;
    }

    public function findContactsByName(string $name): array {
        if (mb_strlen(trim($name)) < 3) {
            return [];
        }

        return $this->contacts(['name' => trim($name)]);
    }

    public function findContactsByNumber(string $number): array {
        if (trim($number) === '' || ! ctype_digit(trim($number))) {
            return [];
        }

        return $this->contacts(['number' => (int) trim($number)]);
    }

    /**
     * @return list<InvoiceLine>
     */
    private function invoiceLines(string $id, string $number, CarbonImmutable $date, string $contactId): array {
        $invoice = $this->getJson('/invoices/' . $id, [], 'Rechnung abrufen');
        $currency = CurrencyCode::tryFrom((string) ($invoice['totalPrice']['currency'] ?? 'EUR')) ?? CurrencyCode::Euro;
        // Belegtexte: Bei Partnerrechnungen steht der Endkunde in Titel,
        // Einleitung oder Schlusstext — nicht in den Positionen.
        $voucherText = trim(implode(' ', array_filter([
            (string) ($invoice['title'] ?? ''),
            (string) ($invoice['introduction'] ?? ''),
            (string) ($invoice['remark'] ?? ''),
        ], static fn(string $part): bool => $part !== '')));
        $recipient = trim((string) ($invoice['address']['name'] ?? ''));

        $lines = [];
        foreach (array_values((array) ($invoice['lineItems'] ?? [])) as $position => $item) {
            if (! is_array($item) || (string) ($item['type'] ?? '') === 'text') {
                continue;
            }

            $unit = is_array($item['unitPrice'] ?? null) ? $item['unitPrice'] : [];
            $net = isset($unit['netAmount']) ? (float) $unit['netAmount'] : null;
            if ($net === null && isset($unit['grossAmount'])) {
                $rate = (float) ($unit['taxRatePercentage'] ?? 0);
                $net = (float) $unit['grossAmount'] / (1 + $rate / 100);
            }
            if ($net === null) {
                continue;
            }

            $discount = (float) ($item['discountPercentage'] ?? 0);
            if ($discount > 0) {
                $net *= 1 - $discount / 100;
            }

            $lines[] = new InvoiceLine(
                voucherId: $id,
                voucherNumber: $number,
                voucherDate: $date,
                voucherType: 'invoice',
                contactId: $contactId,
                position: $position + 1,
                name: (string) ($item['name'] ?? ''),
                description: (string) ($item['description'] ?? ''),
                quantity: (float) ($item['quantity'] ?? 1),
                unitNet: Money::ofFloat($net, $currency),
                voucherText: $voucherText,
                recipient: $recipient,
                articleId: (string) ($item['id'] ?? ''),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return list<array{id: string, name: string}>
     */
    private function contacts(array $filter): array {
        $body = $this->getJson('/contacts', $filter + ['customer' => 'true', 'page' => 0, 'size' => 25], 'Kontakte suchen');

        $out = [];
        foreach ((array) ($body['content'] ?? []) as $contact) {
            if (! is_array($contact) || empty($contact['id'])) {
                continue;
            }
            $name = (string) ($contact['company']['name'] ?? '');
            if ($name === '' && is_array($contact['person'] ?? null)) {
                $name = trim((string) ($contact['person']['firstName'] ?? '') . ' ' . (string) ($contact['person']['lastName'] ?? ''));
            }
            $out[] = ['id' => (string) $contact['id'], 'name' => $name];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query, string $action): array {
        $attempts = 0;
        do {
            $response = $this->api()->getResponse($this->baseUrl . $path, $query);

            if ($response->status() === 429 && $attempts < 5) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                if ($this->requestInterval > 0) {
                    usleep(max($retryAfter, 1) * 1_000_000);
                }
                $attempts++;

                continue;
            }

            if (! $response->successful()) {
                throw LexofficeApiException::fromResponse($response, 'Lexoffice', $action);
            }

            $json = $response->json();

            return is_array($json) ? $json : [];
        } while ($attempts <= 5);

        throw new RuntimeException('Lexoffice-Anfrage nach Ratelimit-Wiederholungen fehlgeschlagen: ' . $action);
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $this->baseUrl, $this->requestInterval);
            $this->api->setAuthentication(new BearerAuthentication($this->apiKey));
        }

        return $this->api;
    }

    private function date(mixed $value): ?CarbonImmutable {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
