<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphBackupOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Plugins\Msgraph\MsgraphConfig;
use GuzzleHttp\Client as GuzzleClient;

/**
 * OAuth2 Authorization-Code-Grant (+ PKCE) für das Microsoft-Graph-BACKUPZIEL
 * (Feature 017 Phase 32, MVP-363). Getrennt von Kalender- und Intake-Flow:
 * eigener Redirect, eigene Scopes (Files.ReadWrite als engste produktiv
 * verfügbare delegierte Berechtigung — bestätigtes Integrationskonto!).
 */
class MsgraphBackupOAuth {
    public function __construct(private readonly ?GuzzleClient $httpClient = null) {}

    public function grant(): OAuth2AuthorizationCodeGrant {
        $config = MsgraphConfig::resolve();

        return new OAuth2AuthorizationCodeGrant(
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            authorizeUrl: $config['authorize_url'],
            tokenUrl: $config['token_url'],
            redirectUri: route('admin.backup-targets.microsoft.oauth.callback'),
            httpClient: $this->httpClient,
        );
    }

    /** @return list<string> */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', (string) MsgraphConfig::resolve()['backup_scopes'])));
    }

    /** Ohne diesen Scope ist das Ziel `blocked` (keine Sonderwege). */
    public function requiredScope(): string {
        return 'Files.ReadWrite';
    }
}
