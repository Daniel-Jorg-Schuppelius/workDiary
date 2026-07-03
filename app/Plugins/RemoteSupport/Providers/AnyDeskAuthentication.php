<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnyDeskAuthentication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Providers;

use APIToolkit\Contracts\Interfaces\API\RequestAwareAuthenticationInterface;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use LogicException;

/**
 * AnyDesk-HMAC-Authentifizierung für die REST-API v1: jeder Request wird per
 * HMAC-SHA1 mit dem API-Passwort der Lizenz signiert; der request-string
 * bindet Methode, Ressource, Content-Hash, Timestamp und ein Einmal-Token.
 * Request-abhängig, daher {@see RequestAwareAuthenticationInterface}.
 */
class AnyDeskAuthentication implements RequestAwareAuthenticationInterface {
    public function __construct(
        private readonly string $licenseId,
        private readonly string $apiPassword,
    ) {}

    public function getAuthHeadersFor(string $method, string $uri, ?string $body = null): array {
        $resource = parse_url($uri, PHP_URL_PATH);
        $resource = is_string($resource) && $resource !== '' ? $resource : $uri;

        $token = base64_encode(random_bytes(16));
        $timestamp = (string) now()->getTimestamp();
        $contentHash = base64_encode(CryptoHelper::hash($body ?? '', HashAlgorithm::SHA1, true));

        $requestString = implode("\n", [
            strtoupper($method),
            $resource,
            $contentHash,
            $timestamp,
            $token,
        ]);

        $signature = base64_encode(
            hash_hmac('sha1', $requestString, $this->apiPassword, true),
        );

        return [
            'Authorization' => sprintf('AD %s:%s:%s:%s', $this->licenseId, $token, $timestamp, $signature),
        ];
    }

    public function getAuthHeaders(): array {
        throw new LogicException('AnyDesk-Signaturen sind request-abhängig — getAuthHeadersFor() verwenden.');
    }

    public function getType(): string {
        return 'AnyDesk-HMAC';
    }

    public function isValid(): bool {
        return $this->licenseId !== '' && $this->apiPassword !== '';
    }
}
