<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PreflightReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Preflight;

use App\Enums\Print\PreflightStatus;

/**
 * Preflight-Befund (MVP-459): Fehler blockieren die Druckfreigabe,
 * Warnungen nicht. Providerneutral — jedes Prüfwerkzeug liefert sein
 * Ergebnis in dieser Form ab und wird vollständig am Auftrag gespeichert.
 */
final class PreflightReport {
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public function status(): PreflightStatus {
        if ($this->errors !== []) {
            return PreflightStatus::Failed;
        }

        return $this->warnings !== [] ? PreflightStatus::Warnings : PreflightStatus::Passed;
    }

    /** @return array{errors: list<string>, warnings: list<string>} */
    public function findings(): array {
        return ['errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
