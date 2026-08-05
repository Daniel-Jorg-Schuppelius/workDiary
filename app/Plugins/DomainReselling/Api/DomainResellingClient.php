<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\DomainReselling\Api;

use App\Models\Domain\DomainProviderConnection;
use App\Plugins\DomainReselling\DomainResellingConfig;
use App\Plugins\DomainReselling\Support\DomainResponseParser;
use App\Plugins\Support\Domain\{DomainProviderException, DomainResponse};
use App\Plugins\Support\PluginHttpFactory;

/**
 * DomainReselling-Transport auf dem `php-api-toolkit`-Fundament (Feature 083,
 * MVP-385).
 *
 *  - AUSSCHLIESSLICH POST an die feste `call.cgi`-URL — Login/Passwort liegen
 *    als `s_login`/`s_pw` (Parameternamen laut API-Handbuch) im Body, nie in
 *    URL/Query/Logs.
 *  - Antwort ist Plaintext; {@see DomainResponseParser} normalisiert sie und
 *    verlangt den abschließenden `EOF`-Marker. Echte FEHLERantworten kommen
 *    ohne `[RESPONSE]`/`EOF` (live belegt: 530) — ein geparster Fehlercode
 *    wird bei Lese-Befehlen deshalb durchgereicht statt als unklar verworfen.
 *  - Für MUTATIONEN werden Transport-Retries abgeschaltet (kein Doppel-Submit
 *    ohne Idempotenzschlüssel); der unklare Ausgang wird oben reconciled.
 *
 * Der Transport stammt aus der {@see PluginHttpFactory} — Tests ersetzen sie
 * durch {@see \Tests\Support\FakePluginHttp} (Guzzle-MockHandler).
 */
class DomainResellingClient {
    private string $callUrl;

    private float $timeout;

    public function __construct(private readonly DomainProviderConnection $connection) {
        $config = DomainResellingConfig::resolve((int) $connection->organization_id);
        $base = DomainResellingConfig::endpointUrl($connection->environment);
        $this->callUrl = $base . $config['call_path'];
        $this->timeout = (float) $config['timeout'];
    }

    /**
     * Führt einen Provider-Befehl aus.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function call(string $command, array $params = [], bool $mutating = false): DomainResponse {
        $client = app(PluginHttpFactory::class)->client('domainreselling', DomainResellingConfig::endpointUrl($this->connection->environment));
        $client->setTimeout($this->timeout);
        if ($mutating) {
            // Ein einziger Versuch (kein Retry) — Mutationen werden ohne
            // Providerprüfung nie automatisch wiederholt. (Toolkit-Minimum: 1.)
            $client->setMaxRetries(1);
        }

        $form = [
            's_login' => $this->connection->login,
            's_pw' => (string) $this->connection->password,
            'command' => $command,
        ];
        if ($this->connection->default_user !== null && $this->connection->default_user !== '') {
            $form['s_user'] = $this->connection->default_user;
        }
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $form[$key] = (string) $value;
            }
        }

        $response = $client->requestResponse('POST', $this->callUrl, ['form_params' => $form]);

        if ($response->status() >= 400) {
            // Nur Statuscode + Befehl — nie Zugangsdaten/Payload in der Meldung.
            throw new DomainProviderException(
                sprintf('DomainReselling-Transport antwortete mit HTTP %d bei "%s".', $response->status(), $command),
                $command,
            );
        }

        $parsed = DomainResponseParser::parse($response->body());
        if (! $parsed->hasEof) {
            // Fehlerantworten der echten API tragen KEIN EOF. Für Lese-Befehle
            // ist ein geparster Fehlercode ein definitives Ergebnis; nur bei
            // Mutationen (oder ohne Fehlercode) bleibt der Ausgang unklar.
            if ($mutating || $parsed->code < 400) {
                throw DomainProviderException::incomplete($command);
            }
        }

        return $parsed;
    }
}
