<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCredentialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCertificate, LearningIssuerKey};
use App\Models\Organization;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\{Carbon, Str};

/**
 * Verifizierbare Zertifikate nach Open Badges 3.0 (Feature 149, MVP-751).
 *
 * Open Badges 3.0 baut auf dem W3C-Datenmodell für Verifiable Credentials
 * auf. Der Nutzen ist praktisch: ein Auftraggeber kann einen vorgelegten
 * Nachweis **maschinell prüfen**, statt bei uns anzurufen.
 *
 * Bewusste Grenzen dieser Stufe, damit niemand mehr hineinliest als drin ist:
 *  - Signatur mit **Ed25519** über die kanonisierte JSON-Form. Das ist eine
 *    schlichte, nachvollziehbare Signatur — **kein** vollständiges
 *    JSON-LD-Data-Integrity-Proof mit RDF-Kanonisierung.
 *  - Kein DID-Verfahren: Aussteller und Schlüssel werden über HTTPS-URLs
 *    benannt, die auf diese Anwendung zeigen.
 *  - Kein Wallet-Protokoll (OID4VCI). Das Credential wird ausgeliefert, nicht
 *    ausgehandelt.
 *
 * Wer echte OB-3.0-Konformität braucht, hat mit diesem Aufbau die Datenform
 * und den Schlüssel — es fehlt die Proof-Suite, nicht das Modell.
 */
class LearningCredentialService {
    public const ALGORITHM = 'ed25519';

    /** Schlüssel der Organisation holen oder anlegen. */
    public function keyFor(Organization $organization): LearningIssuerKey {
        $existing = LearningIssuerKey::query()
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $pair = sodium_crypto_sign_keypair();

        return LearningIssuerKey::query()->create([
            'organization_id' => $organization->id,
            'algorithm' => self::ALGORITHM,
            'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'private_key' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'key_id' => Str::lower(Str::random(24)),
        ]);
    }

    /**
     * Signiertes Credential zu einem Zertifikat.
     *
     * @return array<string, mixed>
     */
    public function issue(LearningCertificate $certificate): array {
        $organization = $certificate->organization;

        if ($organization === null) {
            return [];
        }

        $key = $this->keyFor($organization);
        $document = $this->document($certificate, $organization, $key);

        $secret = base64_decode($key->private_key, true);

        if ($secret === false || $secret === '') {
            return [];
        }

        $signature = sodium_crypto_sign_detached($this->canonical($document), $secret);

        $document['proof'] = [
            'type' => 'Ed25519Signature2020',
            'created' => Carbon::now()->toIso8601String(),
            'verificationMethod' => route('learning.certificates.issuer-key', ['keyId' => $key->key_id]),
            'proofPurpose' => 'assertionMethod',
            'proofValue' => base64_encode($signature),
        ];

        return $document;
    }

    /**
     * Prüft ein ausgeliefertes Credential gegen den hinterlegten Schlüssel.
     * Genau das tut ein Auftraggeber — der Test dieser Methode ist damit
     * auch der Beleg, dass die Signatur trägt.
     *
     * @param  array<string, mixed>  $credential
     */
    public function verify(array $credential): bool {
        $proof = $credential['proof'] ?? null;
        unset($credential['proof']);

        if (! is_array($proof) || ! is_string($proof['proofValue'] ?? null)) {
            return false;
        }

        // Die Schlüssel-URL endet auf `.json` — die reine ID steht davor.
        $keyId = $this->keyIdFromUrl((string) ($proof['verificationMethod'] ?? ''));
        $key = LearningIssuerKey::query()
            ->withoutGlobalScopes()
            ->where('key_id', $keyId)
            ->first();

        if ($key === null) {
            return false;
        }

        $signature = base64_decode($proof['proofValue'], true);
        $publicKey = base64_decode($key->public_key, true);

        // sodium verlangt nicht-leere Schlüssel und Signaturen — ein leerer
        // Wert ist kein gültiger Nachweis, sondern ein Fehlversuch.
        if (! is_string($signature) || $signature === '' || ! is_string($publicKey) || $publicKey === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $this->canonical($credential), $publicKey);
    }

    /** Schlüssel-ID aus der Verifikations-URL (…/{keyId}.json). */
    private function keyIdFromUrl(string $url): string {
        $last = basename(parse_url($url, PHP_URL_PATH) ?: $url);

        return preg_replace('/\.json$/', '', $last) ?? $last;
    }

    /**
     * Datenteil des Credentials — ohne Proof, damit Signatur und Prüfung
     * dieselbe Grundlage haben. Öffentlich, weil auch der VC-JWT-Weg
     * ({@see LearningJwtCredentialService}) dieselbe Datenform braucht: zwei
     * Nachweisformen über **einem** Credential, nicht zwei Credentials.
     * über dieselbe Form laufen.
     *
     * @return array<string, mixed>
     */
    public function document(LearningCertificate $certificate, Organization $organization, LearningIssuerKey $key): array {
        return [
            '@context' => [
                'https://www.w3.org/ns/credentials/v2',
                'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json',
            ],
            'id' => route('learning.certificates.verify', $certificate->verification_code),
            'type' => ['VerifiableCredential', 'OpenBadgeCredential'],
            'issuer' => [
                'id' => route('learning.certificates.issuer-key', ['keyId' => $key->key_id]),
                'type' => ['Profile'],
                'name' => $organization->name,
            ],
            'validFrom' => $certificate->issued_on->toIso8601String(),
            'validUntil' => $certificate->valid_until?->toIso8601String(),
            'credentialSubject' => [
                'type' => ['AchievementSubject'],
                // Kein Klartext-Name im maschinenlesbaren Teil: der Nachweis
                // soll prüfbar sein, nicht als Personendatensatz kursieren.
                'identifier' => [[
                    'type' => 'IdentityObject',
                    'identityType' => 'name',
                    'hashed' => true,
                    'identityHash' => 'sha256$' . CryptoHelper::hash((string) $certificate->holder_name),
                ]],
                'achievement' => [
                    'id' => route('learning.certificates.verify', $certificate->verification_code),
                    'type' => ['Achievement'],
                    'name' => (string) ($certificate->course->title ?? ''),
                    'description' => (string) ($certificate->course->objectives ?? ''),
                    'criteria' => ['narrative' => (string) ($certificate->course->description ?? '')],
                ],
            ],
            'credentialStatus' => [
                'type' => 'RevocationList',
                'id' => route('learning.certificates.verify', $certificate->verification_code),
                'revoked' => $certificate->isRevoked(),
            ],
        ];
    }

    /**
     * Kanonische Form für die Signatur: Schlüssel rekursiv sortiert, damit
     * dieselbe Aussage immer dieselben Bytes ergibt.
     *
     * @param  array<string, mixed>  $document
     */
    private function canonical(array $document): string {
        $sorted = $this->sortRecursive($document);

        return (string) json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortRecursive(array $value): array {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
