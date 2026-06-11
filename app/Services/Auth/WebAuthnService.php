<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebAuthnService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\Auth\TwoFactorType;
use App\Models\Auth\TwoFactorCredential;
use App\Models\User;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\{AttestationStatementSupportManager, NoneAttestationStatementSupport};
use Webauthn\{AuthenticatorAssertionResponse, AuthenticatorAssertionResponseValidator, AuthenticatorAttestationResponse, AuthenticatorAttestationResponseValidator, AuthenticatorSelectionCriteria, PublicKeyCredential, PublicKeyCredentialCreationOptions, PublicKeyCredentialDescriptor, PublicKeyCredentialParameters, PublicKeyCredentialRequestOptions, PublicKeyCredentialRpEntity, PublicKeyCredentialSource, PublicKeyCredentialUserEntity};
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

/**
 * FIDO2/WebAuthn als zweiter Faktor. Registrierung (Attestation) und Anmeldung
 * (Assertion) gemäß W3C-WebAuthn; die Credentials liegen in `two_factor_credentials`
 * (type=webauthn, `credential_id` + serialisierte PublicKeyCredentialSource in `data`).
 */
class WebAuthnService {
    private SerializerInterface $serializer;

    public function __construct() {
        $attestationSupport = AttestationStatementSupportManager::create();
        $attestationSupport->add(NoneAttestationStatementSupport::create());
        $this->serializer = (new WebauthnSerializerFactory($attestationSupport))->create();
    }

    /**
     * Vertrauenswürdiger Origin: Wenn eine echte Domain in app.url konfiguriert
     * ist, hat diese Vorrang vor dem (fälschbaren) Host-Header des Requests.
     * Nur für den Default/Localhost fällt es auf den Request-Origin zurück, damit
     * lokale Entwicklung weiter funktioniert.
     */
    private function trustedOrigin(string $requestOrigin): string {
        $configured = (string) config('app.url', '');
        $host = parse_url($configured, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            return rtrim($configured, '/');
        }

        return $requestOrigin;
    }

    /** Relying-Party-ID = Host der vertrauenswürdigen Origin, z. B. work.schuppelius.org. */
    public function rpId(string $hostOrOrigin): string {
        $origin = $this->trustedOrigin($hostOrOrigin);
        $host = parse_url($origin, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return preg_replace('/:\d+$/', '', $origin) ?? $origin;
    }

    private function rpEntity(string $host): PublicKeyCredentialRpEntity {
        return PublicKeyCredentialRpEntity::create((string) config('app.name', 'WorkDiary'), $this->rpId($host));
    }

    /** Stabiler, nicht erratbarer User-Handle (binär) je Nutzer. */
    private function userHandle(User $user): string {
        return hash('sha256', 'wauh:' . $user->getKey(), true);
    }

    private function userEntity(User $user): PublicKeyCredentialUserEntity {
        return PublicKeyCredentialUserEntity::create(
            $user->email,
            $this->userHandle($user),
            (string) ($user->name ?: $user->email),
        );
    }

    /**
     * Bereits registrierte WebAuthn-Credentials des Nutzers (für excludeCredentials/allowCredentials).
     *
     * @return list<PublicKeyCredentialSource>
     */
    private function credentialSources(User $user): array {
        return array_values($user->twoFactorCredentials()
            ->where('type', TwoFactorType::Webauthn->value)->whereNotNull('confirmed_at')
            ->get()
            ->map(fn(TwoFactorCredential $c) => $this->serializer->deserialize((string) json_encode($c->data), PublicKeyCredentialSource::class, 'json'))
            ->all());
    }

    /** Registrierungs-Optionen (Attestation) als JSON für den Browser. */
    public function creationOptions(User $user, string $host): PublicKeyCredentialCreationOptions {
        $exclude = array_map(
            static fn(PublicKeyCredentialSource $s) => PublicKeyCredentialDescriptor::create($s->type, $s->publicKeyCredentialId),
            $this->credentialSources($user),
        );

        return PublicKeyCredentialCreationOptions::create(
            $this->rpEntity($host),
            $this->userEntity($user),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::create('public-key', -7),   // ES256
                PublicKeyCredentialParameters::create('public-key', -257), // RS256
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_DISCOURAGED,
            ),
            excludeCredentials: $exclude,
        );
    }

    /** Anmelde-Optionen (Assertion) für den geparkten Nutzer. */
    public function requestOptions(User $user, string $host): PublicKeyCredentialRequestOptions {
        $allow = array_map(
            static fn(PublicKeyCredentialSource $s) => PublicKeyCredentialDescriptor::create($s->type, $s->publicKeyCredentialId),
            $this->credentialSources($user),
        );

        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $this->rpId($host),
            allowCredentials: $allow,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        );
    }

    public function optionsToJson(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string {
        return $this->serializer->serialize($options, 'json');
    }

    public function creationOptionsFromJson(string $json): PublicKeyCredentialCreationOptions {
        return $this->serializer->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');
    }

    public function requestOptionsFromJson(string $json): PublicKeyCredentialRequestOptions {
        return $this->serializer->deserialize($json, PublicKeyCredentialRequestOptions::class, 'json');
    }

    /**
     * Registrierung abschliessen: Attestation prüfen und Credential speichern.
     *
     * @throws \Throwable bei ungültiger Attestation
     */
    public function verifyRegistration(User $user, string $browserJson, PublicKeyCredentialCreationOptions $options, string $host, ?string $label = null): TwoFactorCredential {
        $credential = $this->serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');
        $response = $credential->response;
        if (! $response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Keine Attestation-Antwort.');
        }

        $validator = AuthenticatorAttestationResponseValidator::create($this->ceremonyFactory($host)->creationCeremony());
        $source = $validator->check($response, $options, $this->rpId($host));

        return $user->twoFactorCredentials()->create([
            'type' => TwoFactorType::Webauthn->value,
            'label' => $label ?: __('Sicherheitsschlüssel'),
            'credential_id' => $this->b64u($source->publicKeyCredentialId),
            'data' => $this->sourceToArray($source),
            'confirmed_at' => now(),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Anmeldung prüfen: Assertion gegen das gespeicherte Credential validieren.
     *
     * @throws \Throwable bei ungültiger Assertion
     */
    public function verifyAssertion(User $user, string $browserJson, PublicKeyCredentialRequestOptions $options, string $host): bool {
        $credential = $this->serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');
        $response = $credential->response;
        if (! $response instanceof AuthenticatorAssertionResponse) {
            return false;
        }

        $stored = $user->twoFactorCredentials()
            ->where('type', TwoFactorType::Webauthn->value)
            ->where('credential_id', $this->b64u($credential->rawId))
            ->whereNotNull('confirmed_at')->first();
        if ($stored === null) {
            return false;
        }

        $source = $this->serializer->deserialize((string) json_encode($stored->data), PublicKeyCredentialSource::class, 'json');
        $validator = AuthenticatorAssertionResponseValidator::create($this->ceremonyFactory($host)->requestCeremony());
        $updated = $validator->check($source, $response, $options, $this->rpId($host), $source->userHandle);

        // Aktualisierten Zähler (Replay-Schutz) zurückschreiben.
        $stored->forceFill(['data' => $this->sourceToArray($updated), 'last_used_at' => now()])->save();

        return true;
    }

    private function ceremonyFactory(string $origin): CeremonyStepManagerFactory {
        $factory = new CeremonyStepManagerFactory();
        // Erlaubte Origin = vertrauenswürdige (konfigurierte) Origin, konsistent
        // zur rpId(); ein gefälschter Host-Header läuft damit ins Leere.
        $factory->setAllowedOrigins([$this->trustedOrigin($origin)]);

        return $factory;
    }

    /** @return array<string, mixed> */
    private function sourceToArray(\Webauthn\CredentialRecord $source): array {
        /** @var array<string, mixed> $arr */
        $arr = json_decode($this->serializer->serialize($source, 'json'), true);

        return $arr;
    }

    /** base64url-Kodierung der binären Credential-ID. */
    private function b64u(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
