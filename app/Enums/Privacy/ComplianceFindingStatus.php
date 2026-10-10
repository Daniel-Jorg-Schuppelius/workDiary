<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Lückenbefunds der Datenschutz-Analyse; die Reihenfolge der
 * Fälle ist die der Ampel (offene Lücken zuerst). Ohne Übergangstabelle:
 * Analyse und manuelle Entscheidung schreiben aus jedem Stand.
 */
enum ComplianceFindingStatus: string implements HasLabel {
    use HasOptions;

    case Missing = 'missing';
    case Expiring = 'expiring';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Required = 'required';
    case InReview = 'in_review';
    case DeviationAccepted = 'deviation_accepted';
    case Present = 'present';
    case NotApplicable = 'not_applicable';

    /**
     * Von Hand setzbar; „fehlt“ öffnet einen entschiedenen Befund wieder.
     *
     * @return list<self>
     */
    public static function manual(): array {
        return [self::Present, self::InReview, self::NotApplicable, self::DeviationAccepted, self::Missing];
    }

    /**
     * Von der Analyse gesetzte Lücken — nur sie schließt ein späterer Lauf von selbst.
     *
     * @return list<self>
     */
    public static function detected(): array {
        return [self::Missing, self::Expiring];
    }

    public function isManual(): bool {
        return in_array($this, self::manual(), true);
    }

    public function needsJustification(): bool {
        return in_array($this, [self::NotApplicable, self::DeviationAccepted], true);
    }

    public function label(): string {
        return match ($this) {
            self::Missing => (string) __('enums.privacy.compliance_finding_status.missing'),
            self::Expiring => (string) __('enums.privacy.compliance_finding_status.expiring'),
            self::Required => (string) __('enums.privacy.compliance_finding_status.required'),
            self::InReview => (string) __('enums.privacy.compliance_finding_status.in_review'),
            self::DeviationAccepted => (string) __('enums.privacy.compliance_finding_status.deviation_accepted'),
            self::Present => (string) __('enums.privacy.compliance_finding_status.present'),
            self::NotApplicable => (string) __('enums.privacy.compliance_finding_status.not_applicable'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Missing => 'error',
            self::Expiring, self::Required => 'warning',
            self::InReview => 'info',
            self::Present => 'success',
            self::DeviationAccepted, self::NotApplicable => 'ghost',
        };
    }
}
