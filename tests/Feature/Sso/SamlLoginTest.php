<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SamlLoginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\SsoProtocol;
use App\Models\{Organization, SsoConnection, SsoIdentity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\{XMLSecurityDSig, XMLSecurityKey};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-121: SAML-2.0-SP. Responses werden zur Laufzeit mit dem Test-IdP-
 * Zertifikat (tests/Fixtures/sso) gebaut und die Assertion via xmlseclibs
 * signiert — bewusst mit echten Zeitstempeln (php-saml nutzt time(), kein
 * Carbon). Verifiziert Signaturpflicht, Audience, InResponseTo/unsolicited,
 * Replay-Cache, Zertifikatsrotation, NameID-Pflicht und Mandantengrenze.
 */
final class SamlLoginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const IDP_ENTITY_ID = 'https://idp.example/saml';
    private const IDP_SSO_URL = 'https://idp.example/saml/sso';

    private SsoConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);

        $this->connection = SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Saml->value,
            'label' => 'Test-IdP',
            'active' => true,
            'idp_entity_id' => self::IDP_ENTITY_ID,
            'idp_sso_url' => self::IDP_SSO_URL,
            'idp_certificate' => (string) file_get_contents(base_path('tests/Fixtures/sso/idp_cert.pem')),
        ]);
    }

    private function spEntityId(): string {
        return route('sso.saml.metadata', ['slug' => $this->organization->slug]);
    }

    private function acsUrl(): string {
        return route('sso.saml.acs', ['slug' => $this->organization->slug]);
    }

    /**
     * Baut eine signierte SAML-Response (Assertion signiert, exklusive C14N —
     * dieselbe Methode, die php-saml selbst zum Signieren nutzt).
     *
     * @param array{
     *     nameId?: string|null,
     *     inResponseTo?: string,
     *     audience?: string,
     *     signAssertion?: bool,
     *     keyFile?: string,
     *     assertionId?: string,
     * } $options
     */
    private function samlResponse(array $options = []): string {
        $nameId = array_key_exists('nameId', $options) ? $options['nameId'] : 'user@example.org';
        $inResponseTo = $options['inResponseTo'] ?? 'REQ_test';
        $audience = $options['audience'] ?? $this->spEntityId();
        $assertionId = $options['assertionId'] ?? ('_assertion' . bin2hex(random_bytes(8)));
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $notBefore = gmdate('Y-m-d\TH:i:s\Z', time() - 300);
        $notOnOrAfter = gmdate('Y-m-d\TH:i:s\Z', time() + 300);

        $nameIdXml = $nameId === null
            ? ''
            : '<saml:Subject>'
                . '<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">' . htmlspecialchars($nameId) . '</saml:NameID>'
                . '<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">'
                . '<saml:SubjectConfirmationData InResponseTo="' . $inResponseTo . '" NotOnOrAfter="' . $notOnOrAfter . '"/>'
                . '</saml:SubjectConfirmation>'
                . '</saml:Subject>';

        $assertion = '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" xmlns:xs="http://www.w3.org/2001/XMLSchema" '
            . 'ID="' . $assertionId . '" Version="2.0" IssueInstant="' . $now . '">'
            . '<saml:Issuer>' . self::IDP_ENTITY_ID . '</saml:Issuer>'
            . $nameIdXml
            . '<saml:Conditions NotBefore="' . $notBefore . '" NotOnOrAfter="' . $notOnOrAfter . '">'
            . '<saml:AudienceRestriction><saml:Audience>' . htmlspecialchars($audience) . '</saml:Audience></saml:AudienceRestriction>'
            . '</saml:Conditions>'
            . '<saml:AuthnStatement AuthnInstant="' . $now . '" SessionIndex="_session1">'
            . '<saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</saml:AuthnContextClassRef></saml:AuthnContext>'
            . '</saml:AuthnStatement>'
            . '</saml:Assertion>';

        if ($options['signAssertion'] ?? true) {
            $key = (string) file_get_contents(base_path('tests/Fixtures/sso/' . ($options['keyFile'] ?? 'idp_key.pem')));
            $cert = (string) file_get_contents(base_path('tests/Fixtures/sso/' . (($options['keyFile'] ?? 'idp_key.pem') === 'idp_key_next.pem' ? 'idp_cert_next.pem' : 'idp_cert.pem')));
            $assertion = Utils::addSign($assertion, $key, $cert, XMLSecurityKey::RSA_SHA256, XMLSecurityDSig::SHA256);

            // addSign hängt die Signatur bei Assertions VOR den Issuer —
            // das SAML-Schema verlangt sie danach. Verschieben ist sicher:
            // die Enveloped-Signatur schließt sich selbst vom Digest aus.
            $dom = new \DOMDocument();
            $dom->loadXML($assertion);
            $signature = $dom->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature')->item(0);
            $issuer = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0);
            if ($signature !== null && $issuer !== null && $issuer->parentNode !== null) {
                $issuer->parentNode->insertBefore($signature, $issuer->nextSibling);
            }
            $assertion = (string) $dom->saveXML($dom->documentElement);
        }

        $response = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            . 'ID="_response' . bin2hex(random_bytes(8)) . '" Version="2.0" IssueInstant="' . $now . '" InResponseTo="' . $inResponseTo . '">'
            . '<saml:Issuer>' . self::IDP_ENTITY_ID . '</saml:Issuer>'
            . '<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            . $assertion
            . '</samlp:Response>';

        return base64_encode($response);
    }

    /** POST an den ACS mit geparkter AuthnRequest-ID (SP-initiiert). */
    private function postAcs(string $samlResponse, ?string $requestId = 'REQ_test'): \Illuminate\Testing\TestResponse {
        $test = $requestId === null
            ? $this
            : $this->withSession(['sso' => ['saml' => ['connection_id' => $this->connection->id, 'request_id' => $requestId]]]);

        return $test->post($this->acsUrl(), ['SAMLResponse' => $samlResponse]);
    }

    public function test_login_with_signed_assertion_succeeds(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse())->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_start_redirects_to_idp_and_parks_request_id(): void {
        $response = $this->get(route('sso.start', ['slug' => $this->organization->slug]));

        $response->assertRedirect();
        $this->assertStringStartsWith(self::IDP_SSO_URL . '?SAMLRequest=', (string) $response->headers->get('Location'));
        $this->assertNotEmpty(session('sso.saml.request_id'));
    }

    public function test_unsigned_assertion_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse(['signAssertion' => false]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_signer_certificate_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        // Mit dem Rotations-Schlüssel signiert, aber NUR das Erstzertifikat hinterlegt.
        $this->postAcs($this->samlResponse(['keyFile' => 'idp_key_next.pem']))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_certificate_rotation_accepts_successor_certificate(): void {
        $this->connection->forceFill([
            'idp_certificate_next' => (string) file_get_contents(base_path('tests/Fixtures/sso/idp_cert_next.pem')),
        ])->save();

        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse(['keyFile' => 'idp_key_next.pem']))->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_audience_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse(['audience' => 'https://other-sp.example']))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unsolicited_response_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        // Keine geparkte AuthnRequest-ID in der Session (IdP-initiiert).
        $this->postAcs($this->samlResponse(), requestId: null)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_in_response_to_mismatch_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse(['inResponseTo' => 'REQ_other']))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_assertion_replay_is_rejected(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        SsoIdentity::query()->create([
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);

        $payload = $this->samlResponse(['assertionId' => '_assertion_replay']);

        $this->postAcs($payload)->assertRedirect();
        $this->assertAuthenticatedAs($user);

        \Illuminate\Support\Facades\Auth::logout();

        // Dieselbe Assertion erneut (frisch geparkte Request-ID): Replay.
        $this->postAcs($payload)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_missing_name_id_gives_clear_error(): void {
        $this->postAcs($this->samlResponse(['nameId' => null]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unknown_subject_is_rejected_without_email_optin(): void {
        User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse())->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_email_optin_links_saml_name_id(): void {
        $this->connection->forceFill(['allow_email_link' => true])->save();
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'user@example.org',
        ]);

        $this->postAcs($this->samlResponse())->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('sso_identities', [
            'sso_connection_id' => $this->connection->id,
            'user_id' => $user->id,
            'subject' => 'user@example.org',
        ]);
    }

    public function test_metadata_endpoint_returns_sp_metadata(): void {
        $response = $this->get(route('sso.saml.metadata', ['slug' => $this->organization->slug]));

        $response->assertOk();
        $this->assertStringContainsString('entityID="' . $this->spEntityId() . '"', $response->getContent());
        $this->assertStringContainsString($this->acsUrl(), $response->getContent());
    }

    public function test_metadata_of_unknown_org_is_not_found(): void {
        $this->get('/sso/gibt-es-nicht/saml/metadata')->assertNotFound();
    }
}
