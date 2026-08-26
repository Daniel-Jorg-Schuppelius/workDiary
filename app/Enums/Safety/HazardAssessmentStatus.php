<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Safety;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Statusmaschine einer Gefährdungsbeurteilung (§ 5 ArbSchG, Feature 132):
 * Entwurf → in Prüfung → freigegeben → archiviert. Die Freigabe friert den
 * Stand ein; Änderungen erzeugen eine Folgeversion (HazardAssessmentService),
 * die abgelöste Version wird archiviert.
 */
enum HazardAssessmentStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case InReview = 'inReview';
    case Approved = 'approved';
    case Archived = 'archived';

    public function label(): string {
        return (string) __('enums.safety.assessment-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'warning',
            self::InReview => 'info',
            self::Approved => 'success',
            self::Archived => 'ghost',
        };
    }

    /** Inhalt (Kopf + Positionen) noch änderbar? Nur vor der Freigabe. */
    public function isEditable(): bool {
        return $this === self::Draft || $this === self::InReview;
    }

    /**
     * Erlaubte Folgezustände der Statusmaschine.
     *
     * @return list<HazardAssessmentStatus>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::InReview],
            self::InReview => [self::Draft, self::Approved],
            self::Approved => [self::Archived],
            self::Archived => [],
        };
    }
}
