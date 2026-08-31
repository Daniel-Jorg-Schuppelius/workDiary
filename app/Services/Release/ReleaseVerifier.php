<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseVerifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Release;

use App\Services\Isms\SbomGenerator;
use App\Services\Licensing\LicenseService;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Verifiziert ein Release-Manifest (Feature 022, MVP):
 *
 *  1. Prüfsummen-Integrität: jede im Manifest gelistete Artefakt-Prüfsumme
 *     wird gegen die aktuelle Datei neu berechnet und verglichen (erkennt
 *     manipulierte/abweichende composer.lock, package-lock.json, SBOM).
 *  2. Signatur: ist das Manifest mit Ed25519 signiert, wird die Signatur
 *     gegen den (versiegelten oder konfigurierten) Public Key geprüft — mit
 *     denselben Primitiven wie das Lizenzsystem (`sodium_crypto_sign_*`).
 *
 * Liefert ein {@see ReleaseVerificationResult} mit Einzelbefunden; eine
 * fehlende Datei oder ein Hash-Mismatch macht das Ergebnis ungültig, ebenso
 * eine vorhandene, aber falsche Signatur. Ein unsigniertes Manifest ist
 * gültig, solange die Prüfsummen stimmen (Signatur als Zusatzschicht).
 */
class ReleaseVerifier {
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function verify(array $manifest): ReleaseVerificationResult {
        $issues = [];

        // --- Artefakt-Prüfsummen ---
        $artifacts = is_array($manifest['artifacts'] ?? null) ? $manifest['artifacts'] : [];
        $checkedArtifacts = 0;
        foreach ($artifacts as $artifact) {
            if (! is_array($artifact) || ! isset($artifact['name'], $artifact['sha256'])) {
                $issues[] = 'Artefakt-Eintrag ist unvollständig.';

                continue;
            }
            $checkedArtifacts++;
            $name = (string) $artifact['name'];
            $expected = (string) $artifact['sha256'];
            $path = $this->resolveArtifactPath($name);

            if ($path === null || ! is_file($path) || ! is_readable($path)) {
                $issues[] = sprintf('Artefakt "%s" fehlt oder ist nicht lesbar.', $name);

                continue;
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                $issues[] = sprintf('Artefakt "%s" konnte nicht gelesen werden.', $name);

                continue;
            }
            $actual = CryptoHelper::hash($contents);
            if (! hash_equals($expected, $actual)) {
                $issues[] = sprintf('Prüfsumme von "%s" weicht ab (erwartet %s, ist %s).', $name, $expected, $actual);
            }
        }

        // --- Signatur ---
        $signatureBlock = is_array($manifest['signature'] ?? null) ? $manifest['signature'] : [];
        $signed = ($signatureBlock['signed'] ?? false) === true && is_string($signatureBlock['value'] ?? null);
        $signatureValid = null;

        if ($signed) {
            $signatureValid = $this->verifySignature($manifest, (string) $signatureBlock['value'], $signatureBlock['public_key'] ?? null);
            if ($signatureValid === false) {
                $issues[] = 'Signatur ist ungültig (Manifest wurde verändert oder mit fremdem Schlüssel signiert).';
            }
            // `null` bleibt `null`: ohne konfigurierten Herausgeber-Schlüssel
            // ist die Signatur **nicht prüfbar** (Sicherheitsscan 2026-08-23,
            // S-52). Das ist weder „gültig" — so las es sich vorher, weil der
            // im Manifest eingebettete Schlüssel akzeptiert wurde — noch
            // „ungültig": am Manifest muss nichts falsch sein. Es zählt
            // deshalb auch nicht als Befund; eine Installation ohne
            // LICENSE_PUBLIC_KEY ist der Normalfall und kein Mangel.
        }

        return new ReleaseVerificationResult(
            valid: $issues === [],
            signed: $signed,
            signatureValid: $signatureValid,
            checkedArtifacts: $checkedArtifacts,
            issues: $issues,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return bool|null  true/false bei Prüfung; null, wenn kein Public Key vorliegt.
     */
    private function verifySignature(array $manifest, string $signatureB64, mixed $embeddedPublicKey): ?bool {
        $signature = LicenseService::b64Decode($signatureB64);
        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $publicKey = $this->resolvePublicKey(is_string($embeddedPublicKey) ? $embeddedPublicKey : null);
        if ($publicKey === null) {
            return null;
        }

        // Kanonische Rekonstruktion: Signatur-Block entfernen, Rest exakt wie
        // beim Signieren serialisieren.
        unset($manifest['signature']);
        $canonical = ReleaseManifestService::canonicalJson($manifest);

        return sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
    }

    /**
     * Public Key (raw 32 Byte) — ausschließlich der versiegelte oder
     * konfigurierte Key der Instanz.
     *
     * **Der im Manifest eingebettete Key wird NICHT mehr benutzt**
     * (Sicherheitsscan 2026-08-23, S-52). Er beweist nichts: wer das Manifest
     * schreiben kann, schreibt auch den Key hinein — und `release.json` liegt
     * in einem Verzeichnis, das der Web-Nutzer beschreiben kann. Ein mit einem
     * beliebigen Schlüssel signiertes Manifest galt damit als „signiert und
     * gültig", während die Feature-Doku „Neu-Signieren ohne Herausgeber-Key
     * unmöglich" versprach. Ohne konfigurierten Key ist die Signatur **nicht
     * prüfbar** — und das ist etwas anderes als gültig.
     *
     * @return non-empty-string|null
     */
    private function resolvePublicKey(?string $embeddedB64): ?string {
        $b64 = (string) config('license.public_key', '');
        if (\App\Services\Licensing\LicenseSeal::isSealed()) {
            $b64 = \App\Services\Licensing\LicenseSeal::publicKey();
        }
        if ($b64 === '') {
            return null;
        }

        $raw = LicenseService::b64Decode($b64);

        return ($raw !== null && strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) ? $raw : null;
    }

    private function resolveArtifactPath(string $name): ?string {
        return match ($name) {
            'composer.lock' => base_path('composer.lock'),
            'package-lock.json' => base_path('package-lock.json'),
            'sbom' => \Illuminate\Support\Facades\Storage::disk('local')->path('sbom/' . SbomGenerator::latestAlias()),
            'integrity' => \Illuminate\Support\Facades\Storage::disk('local')->path(CodeIntegrityService::STORAGE_PATH),
            default => null,
        };
    }
}
