<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseVerificationResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Release;

/**
 * Ergebnis einer Release-Manifest-Verifikation ({@see ReleaseVerifier}).
 */
final class ReleaseVerificationResult {
    /**
     * @param  bool       $valid            Gesamturteil (Prüfsummen + ggf. Signatur ok).
     * @param  bool       $signed           Trägt das Manifest eine Signatur?
     * @param  bool|null  $signatureValid   true/false bei vorhandener Signatur, sonst null.
     * @param  int        $checkedArtifacts Anzahl geprüfter Artefakte.
     * @param  list<string>  $issues        Gefundene Probleme (leer = sauber).
     */
    public function __construct(
        public readonly bool $valid,
        public readonly bool $signed,
        public readonly ?bool $signatureValid,
        public readonly int $checkedArtifacts,
        public readonly array $issues,
    ) {}

    /** @return array{valid: bool, signed: bool, signature_valid: bool|null, checked_artifacts: int, issues: list<string>} */
    public function toArray(): array {
        return [
            'valid' => $this->valid,
            'signed' => $this->signed,
            'signature_valid' => $this->signatureValid,
            'checked_artifacts' => $this->checkedArtifacts,
            'issues' => $this->issues,
        ];
    }
}
