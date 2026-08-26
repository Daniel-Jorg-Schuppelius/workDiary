<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevenIoSmsClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevenIo\Api;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\SevenIo\{SevenIoConfig, SevenIoPlugin};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\Sms\SmsSendResult;
use Illuminate\Http\Client\Response;

/**
 * seven.io-Gateway auf dem `php-api-toolkit`-Fundament (Feature 147).
 *
 * **Kein Retry auf dem Versand.** `postJson()` läuft ohne
 * `retry_non_idempotent` und ohne Idempotency-Key — seven.io kennt keinen
 * solchen Vertrag, ein zweiter POST wäre also eine zweite SMS mit zweiter
 * Rechnungszeile. Seit api-toolkit v2.9.2 ist genau das der Default: ein
 * bereits gesendetes POST wird nicht wiederholt, Fehler VOR dem Senden
 * (DNS/Connect/TLS) dagegen schon — die richtige Grenze für diesen Fall.
 */
class SevenIoSmsClient {
    /** Antwortcodes, die eine angenommene Nachricht bedeuten. */
    private const ACCEPTED = ['100', '101'];

    private ?PluginApiClient $api = null;

    /**
     * @param  array{enabled: bool, api_key: ?string, api_base: string, from: ?string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $recipientE164, string $text, string $reference): SmsSendResult {
        $payload = [
            'to' => $recipientE164,
            'text' => $text,
            'json' => 1,
            // Fremdschlüssel des Versands: taucht in Zustellquittungen wieder
            // auf und macht sie der Dispatch-Log-Zeile zuordenbar.
            'foreign_id' => mb_substr($reference, 0, 64),
        ];
        $from = (string) ($this->config['from'] ?? '');
        if ($from !== '') {
            $payload['from'] = $from;
        }

        $response = $this->api()->postJson($this->config['api_base'] . '/sms', $payload);
        if (! $response->successful()) {
            return SmsSendResult::failed('http_' . $response->status());
        }

        return $this->interpret($response);
    }

    /** Guthabenabfrage — der billigste echte Aufruf für den Healthcheck. */
    public function checkCredentials(): bool {
        return $this->api()->getResponse($this->config['api_base'] . '/balance')->successful();
    }

    /**
     * seven.io antwortet auch im Fehlerfall mit HTTP 200 und packt den
     * Zustand in `success` — deshalb wird der Code ausgewertet, nicht der
     * Status. Alles außerhalb von 100/101 ist ein Fehlschlag und darf nicht
     * als „versendet" ins Budget wandern.
     */
    private function interpret(Response $response): SmsSendResult {
        /** @var array<string, mixed> $body */
        $body = (array) ($response->json() ?? []);
        $code = (string) ($body['success'] ?? '');

        if (! in_array($code, self::ACCEPTED, true)) {
            return SmsSendResult::failed($code !== '' ? 'seven_' . $code : 'seven_unknown');
        }

        /** @var array<int, array<string, mixed>> $messages */
        $messages = array_values(array_filter((array) ($body['messages'] ?? []), 'is_array'));
        $first = $messages[0] ?? [];

        return SmsSendResult::sent(
            isset($first['id']) ? mb_substr((string) $first['id'], 0, 120) : null,
            (int) ($first['parts'] ?? 1),
        );
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(SevenIoPlugin::ID, $this->config['api_base']);
            $this->api->setAuthentication(new ApiKeyAuthentication((string) ($this->config['api_key'] ?? ''), 'X-Api-Key'));
        }

        return $this->api;
    }

    public static function forOrganization(?int $organizationId = null): self {
        return new self(SevenIoConfig::resolve($organizationId));
    }
}
