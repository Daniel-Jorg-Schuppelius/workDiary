<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractNegotiationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Vertragsverhandlung (Feature 068, MVP-195): Entwurf und Gegenentwurf, Freigabe, dann Abschluss oder Ablehnung. */
enum ApplicationContractNegotiationStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Counter = 'counter';
    case Approved = 'approved';
    case Concluded = 'concluded';
    case Declined = 'declined';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /**
     * Abgeschlossen wird nur nach vollständiger Freigabe; eine neue Version
     * holt auch eine freigegebene Verhandlung zurück in die Prüfung.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::InReview, self::Counter, self::Approved, self::Declined],
            self::InReview => [self::Counter, self::Approved, self::Declined],
            self::Counter => [self::InReview, self::Approved, self::Declined],
            self::Approved => [self::InReview, self::Counter, self::Concluded, self::Declined],
            self::Concluded, self::Declined => [],
        };
    }
}
