<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SipgateSmsClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sipgate\Api;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\Sipgate\{SipgateConfig, SipgatePlugin};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\Sms\SmsSendResult;

/**
 * sipgate-Gateway auf dem `php-api-toolkit`-Fundament (Feature 147).
 *
 * **Kein Retry auf dem Versand** — dieselbe Begründung wie bei seven.io:
 * sipgate kennt keinen Idempotency-Key, ein wiederholter POST ist eine zweite
 * SMS. `retry_non_idempotent` wird deshalb nirgends gesetzt; der
 * api-toolkit-Default seit v2.9.2 (kein Retry nach gesendetem POST,
 * Vor-Send-Fehler weiterhin ja) ist genau richtig.
 *
 * Die API antwortet mit `204 No Content` — es gibt keine Nachrichten-ID und
 * keinen Zustellstatus; im Dispatch-Log bleibt es deshalb bei `sent`
 * („vom Gateway angenommen"), was hier die ehrliche Aussage ist.
 */
class SipgateSmsClient {
    private ?PluginApiClient $api = null;

    /**
     * @param  array{enabled: bool, token_id: ?string, token: ?string, api_base: string, sms_id: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $recipientE164, string $text, string $reference): SmsSendResult {
        $response = $this->api()->postJson($this->config['api_base'] . '/sessions/sms', [
            'smsId' => $this->config['sms_id'],
            'recipient' => $recipientE164,
            'message' => $text,
        ]);

        if (! $response->successful()) {
            return SmsSendResult::failed('http_' . $response->status());
        }

        // Keine Provider-ID im 204 — die Referenz bleibt unsere Log-Zeile.
        return SmsSendResult::sent();
    }

    /** Kontoabfrage — der billigste echte Aufruf für den Healthcheck. */
    public function checkCredentials(): bool {
        return $this->api()->getResponse($this->config['api_base'] . '/account')->successful();
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(SipgatePlugin::ID, $this->config['api_base']);
            $this->api->setAuthentication(new BasicAuthentication(
                (string) ($this->config['token_id'] ?? ''),
                (string) ($this->config['token'] ?? ''),
            ));
        }

        return $this->api;
    }

    public static function forOrganization(?int $organizationId = null): self {
        return new self(SipgateConfig::resolve($organizationId));
    }
}
