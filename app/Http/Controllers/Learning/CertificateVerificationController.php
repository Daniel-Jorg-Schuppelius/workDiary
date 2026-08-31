<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CertificateVerificationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCertificate, LearningIssuerKey};
use App\Services\Learning\{LearningCredentialService, LearningJwtCredentialService};
use Illuminate\Http\{JsonResponse, Response};
use Illuminate\View\View;

/**
 * Öffentliche Prüfseite für Zertifikate (Feature 149, MVP-740).
 *
 * Bewusst **datensparsam**: Kurs, Datum, Gültigkeit und ausstellende
 * Organisation — der Name der Person nur abgekürzt. Wer den Code hat, soll
 * prüfen können, ob ein vorgelegtes Zertifikat echt ist; eine Personenauskunft
 * ist das nicht.
 *
 * Ein widerrufenes Zertifikat verschwindet nicht — die Seite zeigt den
 * Widerruf, sonst ließe sich ein entzogener Nachweis weiterverwenden.
 */
class CertificateVerificationController extends Controller {
    public function __construct(
        private readonly LearningCredentialService $credentials,
    ) {}

    public function show(string $code): View {
        $certificate = LearningCertificate::query()
            ->with(['course', 'organization'])
            ->where('verification_code', $code)
            ->first();

        return view('learning.public.certificate', [
            'certificate' => $certificate,
            'holder' => $certificate !== null ? $this->abbreviate($certificate->holder_name) : null,
        ]);
    }

    /**
     * Maschinenlesbarer Nachweis (Open Badges 3.0). Damit prüft ein
     * Auftraggeber selbst, statt bei uns anzurufen.
     */
    public function credential(string $code): JsonResponse {
        $certificate = LearningCertificate::query()
            ->withoutGlobalScopes()
            ->with(['course', 'organization'])
            ->where('verification_code', $code)
            ->first();

        if ($certificate === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json($this->credentials->issue($certificate));
    }

    /**
     * Dasselbe Zertifikat als **VC-JWT** (Open Badges 3.0, JWT-Weg).
     *
     * Zwei Nachweisformen über **einem** Credential — nicht zwei
     * Credentials. Wer mit einem Standard-Wallet prüft, nimmt diesen Weg;
     * wer die Prüfseite benutzt, merkt davon nichts.
     */
    public function credentialJwt(string $code): Response {
        $certificate = LearningCertificate::query()
            ->withoutGlobalScopes()
            ->with(['course', 'organization'])
            ->where('verification_code', $code)
            ->first();

        if ($certificate === null) {
            return response('not_found', 404);
        }

        $token = app(LearningJwtCredentialService::class)->issue($certificate);

        if ($token === null) {
            return response('not_found', 404);
        }

        return response($token, 200, ['Content-Type' => 'application/jwt']);
    }

    /**
     * Öffentlicher Signaturschlüssel. Ohne ihn ließe sich die Signatur
     * nicht prüfen — der private Teil bleibt selbstverständlich hier.
     */
    public function issuerKey(string $keyId): JsonResponse {
        $key = LearningIssuerKey::query()
            ->withoutGlobalScopes()
            ->with('organization')
            ->where('key_id', $keyId)
            ->first();

        if ($key === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // RSA-Schlüssel werden als JWK ausgeliefert — so verlangt es Open
        // Badges 3.0 für den JWT-Weg. Ed25519 behält seine bisherige Form,
        // damit ausgestellte Zertifikate prüfbar bleiben.
        if ($key->algorithm === LearningJwtCredentialService::ALGORITHM) {
            return response()->json(array_merge(
                app(LearningJwtCredentialService::class)->publicJwk($key),
                ['revoked' => ! $key->isActive()],
            ));
        }

        return response()->json([
            'id' => route('learning.certificates.issuer-key', ['keyId' => $key->key_id]),
            'type' => 'Multikey',
            'controller' => $key->organization->name ?? '',
            'algorithm' => $key->algorithm,
            'publicKeyBase64' => $key->public_key,
            'revoked' => ! $key->isActive(),
        ]);
    }

    /** „Max Mustermann" → „Max M." — genug zum Abgleich, nicht mehr. */
    private function abbreviate(string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '';
        }

        // Gekürzt wird der NACHNAME, nicht das letzte Wort: „Anna Müller Jr."
        // endet auf den Zusatz, und wer nur das letzte Wort kürzt, stellt den
        // Nachnamen vollständig auf eine öffentlich abrufbare Seite. Zusätze
        // sind ein geschlossener Satz (Generation + akademisch); alles, was
        // nicht darin steht, gilt als Namensbestandteil — im Zweifel wird also
        // gekürzt statt veröffentlicht.
        $index = count($parts) - 1;
        while ($index > 0 && $this->isNameSuffix($parts[$index])) {
            $index--;
        }

        $parts[$index] = mb_substr($parts[$index], 0, 1) . '.';

        return implode(' ', $parts);
    }

    /** Namenszusatz (Jr., III, PhD) — kein Namensbestandteil zum Kürzen. */
    private function isNameSuffix(string $part): bool {
        $normalized = mb_strtolower(trim($part, ".,"));

        return in_array($normalized, [
            'jr', 'jun', 'junior', 'sr', 'sen', 'senior',
            'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x',
            'phd', 'md', 'msc', 'bsc', 'mba', 'ba', 'ma', 'llm', 'esq',
        ], true);
    }
}
