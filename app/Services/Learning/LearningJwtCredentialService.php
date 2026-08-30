<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningJwtCredentialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCertificate, LearningIssuerKey};
use App\Models\Organization;
use Illuminate\Support\{Carbon, Str};
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\{JWSBuilder, JWSVerifier};
use Jose\Component\Signature\Serializer\CompactSerializer;
use RuntimeException;

/**
 * Zertifikate als **VC-JWT** ausstellen und prüfen (Feature 149,
 * Fortsetzung MVP-751).
 *
 * **Warum nicht die eingebettete Data-Integrity-Signatur:** Open Badges 3.0
 * lässt beide Nachweisformen zu. Die eingebettete verlangt RDF-Kanonisierung
 * nach RDFC-1.0 — dafür existiert **keine PHP-Implementierung**, und einen
 * Kanonisierungsalgorithmus, dessen Ausgabe direkt in eine Signatur fließt,
 * schreibt man nicht selbst. VC-JWT kommt ohne aus: signiert wird die
 * serialisierte Nutzlast.
 *
 * **RS256 mit dem öffentlichen Schlüssel als JWK** — so verlangt es die
 * Zertifizierungsvorgabe für den JWT-Weg.
 *
 * Die vorhandene Ed25519-Signatur bleibt daneben bestehen: ausgestellte
 * Zertifikate müssen prüfbar bleiben, auch wenn ein zweites Verfahren
 * dazukommt.
 */
class LearningJwtCredentialService {
    public const ALGORITHM = 'rs256';

    /** 2048 Bit ist die Untergrenze, die Prüfer heute noch akzeptieren. */
    private const KEY_BITS = 2048;

    public function __construct(
        private readonly LearningCredentialService $credentials,
    ) {}

    /**
     * RSA-Schlüssel der Organisation holen oder anlegen.
     *
     * Bewusst **neben** dem Ed25519-Schlüssel: verschiedene Verfahren,
     * verschiedene Schlüssel. Einen Schlüssel für zwei Algorithmen zu
     * verwenden wäre nicht möglich und wäre es auch nicht wünschenswert.
     */
    public function keyFor(Organization $organization): LearningIssuerKey {
        $existing = LearningIssuerKey::query()
            ->where('organization_id', $organization->id)
            ->where('algorithm', self::ALGORITHM)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('RSA-Schlüsselpaar ließ sich nicht erzeugen.');
        }

        $privatePem = '';
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['key'])) {
            throw new RuntimeException('Öffentlicher Schlüssel ließ sich nicht lesen.');
        }

        return LearningIssuerKey::query()->create([
            'organization_id' => $organization->id,
            'algorithm' => self::ALGORITHM,
            'public_key' => (string) $details['key'],
            'private_key' => $privatePem,
            'key_id' => Str::lower(Str::random(24)),
        ]);
    }

    /**
     * Zertifikat als kompaktes JWS ausstellen.
     *
     * Die registrierten JWT-Ansprüche spiegeln die Angaben des Credentials
     * (`iss`, `sub`, `nbf`, `exp`, `jti`) — Prüfer lesen sie, ohne die
     * Nutzlast verstehen zu müssen.
     */
    public function issue(LearningCertificate $certificate): ?string {
        $organization = $certificate->organization;

        if ($organization === null) {
            return null;
        }

        $key = $this->keyFor($organization);
        $document = $this->credentials->document($certificate, $organization, $key);

        $payload = [
            'iss' => $document['issuer']['id'] ?? url('/'),
            'jti' => $document['id'] ?? null,
            'sub' => $document['credentialSubject']['id'] ?? null,
            'nbf' => $certificate->issued_on->getTimestamp(),
            'iat' => Carbon::now()->getTimestamp(),
            'vc' => $document,
        ];

        if ($certificate->valid_until !== null) {
            $payload['exp'] = $certificate->valid_until->endOfDay()->getTimestamp();
        }

        $jwk = JWKFactory::createFromKey($key->private_key);

        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))
            ->create()
            ->withPayload((string) json_encode(array_filter($payload, static fn ($v): bool => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->addSignature($jwk, [
                'alg' => 'RS256',
                'typ' => 'vc+ld+jwt',
                'kid' => route('learning.certificates.issuer-key', ['keyId' => $key->key_id]),
            ])
            ->build();

        return (new CompactSerializer())->serialize($jws, 0);
    }

    /**
     * Ein ausgeliefertes VC-JWT prüfen — genau das tut ein Auftraggeber.
     *
     * Der Schlüssel wird über die `kid`-Angabe im Kopf gesucht, nicht über
     * eine mitgelieferte Schlüsseldatei: ein Nachweis, der seinen eigenen
     * Prüfschlüssel mitbringt, beweist nichts.
     */
    public function verify(string $token): bool {
        $serializer = new CompactSerializer();

        try {
            $jws = $serializer->unserialize($token);
        } catch (\Throwable) {
            return false;
        }

        $header = $jws->getSignature(0)->getProtectedHeader();
        $keyId = $this->keyIdFromUrl((string) ($header['kid'] ?? ''));

        $key = LearningIssuerKey::query()
            ->withoutGlobalScopes()
            ->where('algorithm', self::ALGORITHM)
            ->where('key_id', $keyId)
            ->first();

        if ($key === null) {
            return false;
        }

        $jwk = JWKFactory::createFromKey($key->public_key);

        return (new JWSVerifier(new AlgorithmManager([new RS256()])))->verifyWithKey($jws, $jwk, 0);
    }

    /**
     * Öffentlicher Schlüssel als JWK — so verlangt es Open Badges 3.0 für
     * den JWT-Weg.
     *
     * @return array<string, mixed>
     */
    public function publicJwk(LearningIssuerKey $key): array {
        $jwk = JWKFactory::createFromKey($key->public_key)->toPublic()->all();

        return array_merge($jwk, [
            'kid' => route('learning.certificates.issuer-key', ['keyId' => $key->key_id]),
            'alg' => 'RS256',
            'use' => 'sig',
        ]);
    }

    /** Schlüssel-ID aus der Verifikations-URL (…/{keyId}.json). */
    private function keyIdFromUrl(string $url): string {
        $last = basename(parse_url($url, PHP_URL_PATH) ?: $url);

        return preg_replace('/\.json$/', '', $last) ?? $last;
    }
}
