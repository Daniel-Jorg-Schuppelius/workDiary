<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCredentialTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\{LearningCertificate, LearningIssuerKey};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningCredentialService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Verifizierbare Zertifikate nach Open Badges 3.0 (Feature 149, MVP-751).
 *
 * Der Nutzen ist praktisch: ein Auftraggeber prüft einen vorgelegten
 * Nachweis selbst, statt anzurufen. Der Test spielt genau das durch —
 * abholen, prüfen, manipulieren, erneut prüfen.
 */
class LearningCredentialTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): LearningCredentialService {
        return app(LearningCredentialService::class);
    }

    private function certificate(): LearningCertificate {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, [
            'title' => 'Arbeiten in Höhen',
            'certificate_enabled' => true,
            'validity_months' => 24,
        ]);
        $courses->addUnit($course, ['title' => 'Praxis']);
        $courses->release($course->refresh(), null);

        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course->refresh(), $user);
        $enrollments->completeUnit($enrollment, $course->units()->firstOrFail());

        return LearningCertificate::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
    }

    public function test_schluessel_wird_einmal_erzeugt_und_wiederverwendet(): void {
        $first = $this->service()->keyFor($this->organization);
        $second = $this->service()->keyFor($this->organization);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(LearningCredentialService::ALGORITHM, $first->algorithm);
    }

    public function test_privater_schluessel_liegt_verschluesselt_in_der_datenbank(): void {
        $key = $this->service()->keyFor($this->organization);

        $raw = (string) DB::table('learning_issuer_keys')->where('id', $key->id)->value('private_key');

        $this->assertNotSame($key->private_key, $raw, 'Der private Schlüssel darf nicht im Klartext gespeichert sein.');
        $this->assertStringNotContainsString($key->private_key, $raw);
    }

    public function test_privater_schluessel_erscheint_nicht_in_der_serialisierung(): void {
        $key = $this->service()->keyFor($this->organization);

        $this->assertArrayNotHasKey('private_key', $key->toArray());
    }

    public function test_credential_traegt_kurs_und_signatur(): void {
        $certificate = $this->certificate();

        $credential = $this->service()->issue($certificate);

        $this->assertContains('OpenBadgeCredential', $credential['type']);
        $this->assertSame('Arbeiten in Höhen', $credential['credentialSubject']['achievement']['name']);
        $this->assertNotEmpty($credential['proof']['proofValue']);
        $this->assertTrue($this->service()->verify($credential));
    }

    public function test_credential_nennt_den_namen_nur_gehasht(): void {
        $certificate = $this->certificate();

        $credential = $this->service()->issue($certificate);
        $json = json_encode($credential) ?: '';

        $this->assertStringNotContainsString($certificate->holder_name, $json, 'Der Klartext-Name gehört nicht in den maschinenlesbaren Nachweis.');
        $this->assertStringStartsWith('sha256$', $credential['credentialSubject']['identifier'][0]['identityHash']);
    }

    public function test_manipuliertes_credential_faellt_durch(): void {
        $credential = $this->service()->issue($this->certificate());

        $credential['credentialSubject']['achievement']['name'] = 'Ein ganz anderer Kurs';

        $this->assertFalse($this->service()->verify($credential), 'Eine Änderung am Inhalt muss die Signatur brechen.');
    }

    public function test_credential_ohne_signatur_faellt_durch(): void {
        $credential = $this->service()->issue($this->certificate());
        unset($credential['proof']);

        $this->assertFalse($this->service()->verify($credential));
    }

    public function test_endpunkte_liefern_credential_und_oeffentlichen_schluessel(): void {
        $certificate = $this->certificate();

        $credential = $this->get(route('learning.certificates.credential', $certificate->verification_code))
            ->assertOk()
            ->json();

        $this->assertTrue($this->service()->verify($credential));

        $keyId = basename((string) $credential['proof']['verificationMethod']);
        $keyId = str_replace('.json', '', $keyId);

        $key = $this->get(route('learning.certificates.issuer-key', ['keyId' => $keyId]))
            ->assertOk()
            ->json();

        $this->assertSame(
            LearningIssuerKey::query()->where('key_id', $keyId)->firstOrFail()->public_key,
            $key['publicKeyBase64']
        );
        $this->assertArrayNotHasKey('private_key', $key);
    }

    public function test_unbekannter_code_liefert_404(): void {
        $this->get(route('learning.certificates.credential', str_repeat('z', 32)))->assertNotFound();
    }

    // ── VC-JWT (Open Badges 3.0, JWT-Weg) ───────────────────────────────
    //
    // Die eingebettete Data-Integrity-Form verlangt RDF-Kanonisierung nach
    // RDFC-1.0; dafür gibt es in PHP keine Implementierung. Open Badges 3.0
    // lässt deshalb auch VC-JWT zu — RS256, Schlüssel als JWK.

    private function jwtService(): \App\Services\Learning\LearningJwtCredentialService {
        return app(\App\Services\Learning\LearningJwtCredentialService::class);
    }

    public function test_zertifikat_kommt_als_signiertes_jwt(): void {
        $certificate = $this->certificate();

        $token = $this->jwtService()->issue($certificate);

        $this->assertIsString($token);
        // Kompaktes JWS: Kopf, Nutzlast, Signatur.
        $this->assertCount(3, explode('.', (string) $token));
        $this->assertTrue($this->jwtService()->verify((string) $token));
    }

    public function test_manipuliertes_jwt_faellt_durch(): void {
        $certificate = $this->certificate();
        $token = (string) $this->jwtService()->issue($certificate);

        [$header, $payload, $signature] = explode('.', $token);

        // Nutzlast ändern: aus „bestanden" wird ein anderer Name.
        $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $decoded['vc']['credentialSubject']['achievement']['name'] = 'Etwas ganz anderes';
        $tampered = rtrim(strtr(base64_encode((string) json_encode($decoded)), '+/', '-_'), '=');

        $this->assertFalse($this->jwtService()->verify($header . '.' . $tampered . '.' . $signature));
    }

    public function test_rsa_und_ed25519_schluessel_stehen_nebeneinander(): void {
        $certificate = $this->certificate();
        $organization = $certificate->organization;

        $ed = app(\App\Services\Learning\LearningCredentialService::class)->keyFor($organization);
        $rsa = $this->jwtService()->keyFor($organization);

        // Verschiedene Verfahren, verschiedene Schlüssel — ausgestellte
        // Zertifikate müssen prüfbar bleiben, auch wenn eines dazukommt.
        $this->assertNotSame($ed->id, $rsa->id);
        $this->assertSame('ed25519', $ed->algorithm);
        $this->assertSame('rs256', $rsa->algorithm);
    }

    public function test_oeffentlicher_schluessel_wird_als_jwk_ausgeliefert(): void {
        $certificate = $this->certificate();
        $key = $this->jwtService()->keyFor($certificate->organization);

        $response = $this->get(route('learning.certificates.issuer-key', ['keyId' => $key->key_id]));

        $response->assertOk();
        // Open Badges 3.0 verlangt für den JWT-Weg den Schlüssel als JWK.
        $response->assertJsonPath('kty', 'RSA');
        $response->assertJsonPath('alg', 'RS256');
        $this->assertArrayNotHasKey('d', $response->json(), 'Der private Exponent darf nie ausgeliefert werden.');
    }

    public function test_jwt_endpunkt_liefert_den_nachweis(): void {
        $certificate = $this->certificate();

        $response = $this->get(route('learning.certificates.credential-jwt', ['code' => $certificate->verification_code]));

        $response->assertOk();
        $this->assertSame('application/jwt', $response->headers->get('Content-Type'));
        $this->assertTrue($this->jwtService()->verify($response->getContent()));
    }

    public function test_unbekannter_code_liefert_kein_jwt(): void {
        $this->get(route('learning.certificates.credential-jwt', ['code' => 'gibtesnicht']))
            ->assertNotFound();
    }
}
