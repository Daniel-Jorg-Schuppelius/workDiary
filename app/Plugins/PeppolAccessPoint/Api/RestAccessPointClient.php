<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestAccessPointClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PeppolAccessPoint\Api;

use App\Plugins\PeppolAccessPoint\PeppolAccessPointPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use DateTimeImmutable;
use ERechnungToolkit\Contracts\AccessPointClientInterface;
use ERechnungToolkit\Enums\PeppolTransportStatus;
use ERechnungToolkit\Peppol\{InboundDocument, TransportReceipt};
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Generischer REST-Adapter zu einem zertifizierten Peppol-Access-Point-Provider
 * (Feature 066, MVP-734).
 *
 * **Warum generisch:** Zum Bauzeitpunkt liegt kein Providervertrag vor. Statt
 * die Schnittstelle eines bestimmten Anbieters zu raten, kommen Endpunkt-Pfade
 * und JSON-Feldnamen aus der Plugin-Konfiguration; der Pilot stellt sie auf den
 * tatsächlich gewählten Provider ein. Die fachliche Substanz (SBDH-Umschlag,
 * SML/SMP-Auflösung, BIS-Prüfung) liegt im php-erechnung-toolkit und ist von
 * dieser Naht unabhängig.
 *
 * **Kein Retry auf dem Versand:** Peppol-Provider kennen keinen einheitlichen
 * Idempotency-Key — ein wiederholter POST ist eine zweite Zustellung. Es bleibt
 * beim api-toolkit-Default (kein Retry nach gesendetem POST).
 */
final class RestAccessPointClient implements AccessPointClientInterface {
    private ?PluginApiClient $api = null;

    /** @param array<string, mixed> $config {@see \App\Plugins\PeppolAccessPoint\PeppolAccessPointConfig::resolve()} */
    public function __construct(private readonly array $config) {}

    public function isAvailable(): bool {
        if ($this->str('base_url') === '' || $this->str('api_key') === '') {
            return false;
        }

        try {
            return $this->api()->getResponse($this->url($this->str('health_path')))->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function send(string $sbdhEnvelopeXml): TransportReceipt {
        $url = $this->url($this->str('send_path'));
        $payloadField = $this->str('payload_field');

        try {
            $response = $payloadField === ''
                // Provider ohne JSON-Hülle: der Umschlag ist der Body.
                ? $this->api()->requestResponse('post', $url, [
                    'body' => $sbdhEnvelopeXml,
                    'headers' => ['Content-Type' => 'application/xml; charset=UTF-8'],
                ])
                : $this->api()->postJson($url, [$payloadField => $sbdhEnvelopeXml]);
        } catch (Throwable $e) {
            throw new RuntimeException('Peppol-Access-Point nicht erreichbar: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Peppol-Access-Point hat den Versand abgelehnt (HTTP %d): %s',
                $response->status(),
                mb_substr(trim($response->body()), 0, 500),
            ));
        }

        $body = $this->json($response);
        $messageId = (string) ($body[$this->str('message_id_field')] ?? '');
        if ($messageId === '') {
            throw new RuntimeException('Peppol-Access-Point hat keine Nachrichtenkennung geliefert — der Zugangsnachweis wäre wertlos.');
        }

        $status = PeppolTransportStatus::tryFrom(strtolower((string) ($body[$this->str('status_field')] ?? '')))
            // 2xx ohne Statusfeld heißt „angenommen, Zustellung läuft" — nicht
            // „zugestellt": das behauptet der Provider hier nirgends.
            ?? PeppolTransportStatus::PENDING;

        if ($status === PeppolTransportStatus::FAILED || $status === PeppolTransportStatus::REJECTED) {
            return TransportReceipt::failed(
                $messageId,
                (string) ($body['errorMessage'] ?? $body['error'] ?? 'Vom Access Point abgelehnt.'),
                isset($body['errorCode']) ? (string) $body['errorCode'] : null,
                $status,
                $this->timestamp($body),
                $response->body(),
            );
        }

        return new TransportReceipt(
            $messageId,
            $status,
            $this->timestamp($body) ?? new DateTimeImmutable,
            isset($body['instanceIdentifier']) ? (string) $body['instanceIdentifier'] : null,
            isset($body['receiverAccessPoint']) ? (string) $body['receiverAccessPoint'] : null,
            null,
            null,
            $response->body(),
        );
    }

    /** @return list<InboundDocument> */
    public function receive(int $limit = 50): array {
        $url = $this->url($this->str('receive_path'));

        try {
            $response = $this->api()->getResponse($url, ['limit' => $limit]);
        } catch (Throwable $e) {
            throw new RuntimeException('Peppol-Eingang nicht abrufbar: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Peppol-Eingang nicht abrufbar (HTTP %d).', $response->status()));
        }

        $body = $this->json($response);
        $itemsField = $this->str('items_field');
        $items = $body[$itemsField] ?? ($itemsField === '' ? $body : []);
        if (! is_array($items)) {
            return [];
        }

        $documents = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $messageId = (string) ($item[$this->str('message_id_field')] ?? '');
            $envelope = (string) ($item[$this->str('payload_field') ?: 'document'] ?? '');
            if ($messageId === '' || $envelope === '') {
                continue; // unvollständige Zeile: nie halb quittieren
            }
            $documents[] = new InboundDocument(
                $messageId,
                $envelope,
                $this->timestamp($item, 'receivedAt') ?? new DateTimeImmutable,
                json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            );
        }

        return $documents;
    }

    public function acknowledge(string $messageId): bool {
        $path = str_replace(['{messageId}', '{message_id}'], rawurlencode($messageId), $this->str('ack_path'));
        $payload = str_contains($this->str('ack_path'), '{') ? [] : [$this->str('message_id_field') => $messageId];

        try {
            return $this->api()->postJson($this->url($path), $payload)->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $body */
    private function timestamp(array $body, string $key = 'timestamp'): ?DateTimeImmutable {
        $raw = $body[$key] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
    }

    private function url(string $path): string {
        return $this->str('base_url') . '/' . ltrim($path, '/');
    }

    private function str(string $key): string {
        $value = $this->config[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(PeppolAccessPointPlugin::ID, $this->str('base_url'));
            $header = $this->str('auth_header') !== '' ? $this->str('auth_header') : 'Authorization';
            $scheme = $this->str('auth_scheme');
            $this->api->addDefaultHeader($header, $scheme === '' ? $this->str('api_key') : $scheme . ' ' . $this->str('api_key'));
        }

        return $this->api;
    }
}
