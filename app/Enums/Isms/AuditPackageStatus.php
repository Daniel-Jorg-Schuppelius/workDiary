<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditPackageStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Auditpakets (Feature 046, Inkrement E): draft → finalized.
 * Die Finalisierung friert den Datenstand als JSON-Snapshot ein (Datei +
 * SHA-256) und setzt finalized_by/at (046-Prinzip „Freigabe mit
 * Person/Zeitpunkt/Gegenstand"); finalisierte Pakete sind UNVERÄNDERLICH
 * (Model-Guard wie IsmsRiskAssessment).
 */
enum AuditPackageStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string {
        return (string) __('enums.isms.audit-package-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'warning',
            self::Finalized => 'success',
        };
    }
}
