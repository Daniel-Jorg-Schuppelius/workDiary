<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SamlClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\Sso;

use App\Models\SsoConnection;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\{Cache, Log};
use OneLogin\Saml2\{AuthnRequest, Response, Settings, Utils};

/**
 * SAML-2.0-Service-Provider für den SSO-Login (Feature 057, MVP-121) auf
 * onelogin/php-saml ≥ 4.3.2 (xmlseclibs ≥ 3.1.5 gepinnt — Signature-Wrapping-
 * CVEs). Pflicht-Settings laut Anbindungs-Recherche: strict,
 * wantAssertionsSigned (Response-Signatur allein schützt nicht gegen
 * Assertion-Austausch), AudienceRestriction, InResponseTo (unsolicited/
 * IdP-initiated wird abgelehnt), eigener Replay-Cache für Assertion-IDs,
 * x509certMulti für Zertifikatsrotation. SLO ist bewusst SP-lokal.
 */
class SamlClient {
    private const REPLAY_TTL_SECONDS = 600;

    /**
     * Startet SP-initiiertes Login: Redirect-URL zum IdP + AuthnRequest-ID,
     * die für den InResponseTo-Abgleich in der Session geparkt wird.
     *
     * @return array{url: string, request_id: string}
     */
    public function loginRequest(SsoConnection $connection): array {
        $settings = $this->settings($connection);
        $request = new AuthnRequest($settings);

        $url = (string) $connection->idp_sso_url
            . (str_contains((string) $connection->idp_sso_url, '?') ? '&' : '?')
            . http_build_query(['SAMLRequest' => $request->getRequest()]);

        return ['url' => $url, 'request_id' => $request->getId()];
    }

    /**
     * Validiert eine an den ACS gelieferte SAMLResponse (Base64) und liefert
     * Subject (NameID) + optionale E-Mail. `$expectedRequestId` stammt aus der
     * Session — ohne sie gilt die Antwort als unsolicited und wird abgelehnt.
     *
     * @return array{subject: string, email: string|null}
     */
    public function validateResponse(SsoConnection $connection, string $samlResponse, ?string $expectedRequestId): array {
        if ($expectedRequestId === null || $expectedRequestId === '') {
            throw new SsoLoginException(__('sso.error.saml_unsolicited'));
        }

        $settings = $this->settings($connection);

        // onelogin liest die eigene URL (Destination-Prüfung) aus Superglobals;
        // im CLI-/Testkontext deterministisch auf die ACS-URL setzen.
        Utils::setBaseURL(url('/'));

        try {
            $response = new Response($settings, $samlResponse);
            $valid = $response->isValid($expectedRequestId);
        } catch (\Throwable $e) {
            Log::warning('SSO/SAML: Response nicht lesbar.', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            throw new SsoLoginException(__('sso.error.saml_invalid'));
        }

        if (! $valid) {
            Log::warning('SSO/SAML: Response ungültig.', [
                'connection_id' => $connection->id,
                'error' => $response->getErrorException()?->getMessage(),
            ]);

            throw new SsoLoginException(__('sso.error.saml_invalid'));
        }

        $this->rejectReplay($connection, $response);

        $nameId = trim((string) $response->getNameId());
        if ($nameId === '') {
            // ADFS liefert ohne explizite Claim-Rule keine NameID — klare Meldung.
            throw new SsoLoginException(__('sso.error.saml_no_nameid'));
        }

        return ['subject' => $nameId, 'email' => $this->emailFromAttributes($response, $nameId)];
    }

    /** SP-Metadata-XML für die Registrierung beim IdP. */
    public function metadata(SsoConnection $connection): string {
        $settings = $this->settings($connection);
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        if ($errors !== []) {
            throw new SsoLoginException(__('sso.error.saml_settings_invalid'));
        }

        return $metadata;
    }

    /**
     * Konfigurationsprüfung für den Admin-Test-Button: Settings bauen und
     * Zertifikat(e) parsen — wirft {@see SsoLoginException} mit Details.
     */
    public function assertConfigured(SsoConnection $connection): void {
        foreach ($connection->idpCertificates() as $certificate) {
            if (openssl_x509_read(Utils::formatCert($certificate)) === false) {
                throw new SsoLoginException(__('sso.error.saml_certificate_invalid'));
            }
        }

        $this->metadata($connection);
    }

    private function settings(SsoConnection $connection): Settings {
        $certificates = $connection->idpCertificates();
        if ($certificates === [] || ! filled($connection->idp_entity_id) || ! filled($connection->idp_sso_url)) {
            throw new SsoLoginException(__('sso.error.saml_settings_invalid'));
        }

        $organization = $connection->organization()->withoutGlobalScopes()->firstOrFail();
        $slug = (string) $organization->slug;

        $idp = [
            'entityId' => (string) $connection->idp_entity_id,
            'singleSignOnService' => ['url' => (string) $connection->idp_sso_url],
        ];
        if (count($certificates) > 1) {
            // Rotation: altes und neues Signaturzertifikat parallel akzeptieren.
            $idp['x509certMulti'] = ['signing' => $certificates, 'encryption' => []];
        } else {
            $idp['x509cert'] = $certificates[0];
        }

        try {
            return new Settings([
                'strict' => true,
                'sp' => [
                    'entityId' => route('sso.saml.metadata', ['slug' => $slug]),
                    'assertionConsumerService' => ['url' => route('sso.saml.acs', ['slug' => $slug])],
                    'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
                ],
                'idp' => $idp,
                'security' => [
                    'wantAssertionsSigned' => true,
                    'wantNameId' => true,
                    'requestedAuthnContext' => false,
                    'rejectUnsolicitedResponsesWithInResponseTo' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SSO/SAML: Settings ungültig.', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            throw new SsoLoginException(__('sso.error.saml_settings_invalid'));
        }
    }

    /**
     * Replay-Schutz: jede Assertion-ID wird für die Gültigkeitsspanne der
     * Assertion gemerkt; eine zweite Zustellung derselben ID wird abgelehnt
     * (php-saml bringt keinen eigenen Replay-Cache mit).
     */
    private function rejectReplay(SsoConnection $connection, Response $response): void {
        $assertionId = $this->assertionId($response);
        if ($assertionId === null) {
            throw new SsoLoginException(__('sso.error.saml_invalid'));
        }

        $key = 'sso.saml.assertion.' . $connection->id . '.' . CryptoHelper::hash($assertionId, HashAlgorithm::SHA1);
        if (! Cache::add($key, true, self::REPLAY_TTL_SECONDS)) {
            Log::warning('SSO/SAML: Assertion-Replay abgelehnt.', ['connection_id' => $connection->id]);

            throw new SsoLoginException(__('sso.error.saml_invalid'));
        }
    }

    private function assertionId(Response $response): ?string {
        $document = new DOMDocument();
        if (! @$document->loadXML((string) $response->document->saveXML())) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $nodes = $xpath->query('//saml:Assertion/@ID');
        $id = $nodes !== false && $nodes->length > 0 ? trim((string) $nodes->item(0)?->nodeValue) : '';

        return $id !== '' ? $id : null;
    }

    /** E-Mail aus üblichen Attribut-Claims; NameID im E-Mail-Format als Fallback. */
    private function emailFromAttributes(Response $response, string $nameId): ?string {
        $attributes = $response->getAttributes();
        $candidates = [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'urn:oid:0.9.2342.19200300.100.1.3',
            'email',
            'mail',
        ];

        foreach ($candidates as $claim) {
            $value = $attributes[$claim][0] ?? null;
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return $value;
            }
        }

        return filter_var($nameId, FILTER_VALIDATE_EMAIL) !== false ? $nameId : null;
    }
}
