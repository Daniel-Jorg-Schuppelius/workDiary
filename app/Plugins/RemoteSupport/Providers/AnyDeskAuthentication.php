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
 * HMAC-SHA1 mit dem API-Passwort der Lizenz signiert. Der request-string ist
 * `METHOD\nRESOURCE\nTIMESTAMP\nCONTENT_HASH`, wobei RESOURCE Pfad **plus
 * Query-String** ist; der Header hat exakt drei Felder
 * (`AD LICENSE:TIMESTAMP:TOKEN`) — Referenz: github.com/anydesk/rest-api.
 * Request-abhängig, daher {@see RequestAwareAuthenticationInterface}.
 */
class AnyDeskAuthentication implements RequestAwareAuthenticationInterface {
    public function __construct(
        private readonly string $licenseId,
        private readonly string $apiPassword,
    ) {}

    public function getAuthHeadersFor(string $method, string $uri, ?string $body = null): array {
        $path = parse_url($uri, PHP_URL_PATH);
        $resource = is_string($path) && $path !== '' ? $path : $uri;

        $query = parse_url($uri, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $resource .= '?' . $query;
        }

        $timestamp = (string) now()->getTimestamp();
        $contentHash = base64_encode(CryptoHelper::hash($body ?? '', HashAlgorithm::SHA1, true));

        $requestString = implode("\n", [
            strtoupper($method),
            $resource,
            $timestamp,
            $contentHash,
        ]);

        $signature = base64_encode(
            hash_hmac('sha1', $requestString, $this->apiPassword, true),
        );

        return [
            'Authorization' => sprintf('AD %s:%s:%s', $this->licenseId, $timestamp, $signature),
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
