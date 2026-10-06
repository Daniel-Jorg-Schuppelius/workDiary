<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyInvitationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Survey;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Umfrage-Einladung (Feature 090). */
enum SurveyInvitationStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Created = 'created';
    case Sent = 'sent';
    case Responded = 'responded';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben — eine Altzeile darf den Cast nicht sprengen. */
    case Expired = 'expired';

    public function label(): string {
        return match ($this) {
            self::Created => __('erstellt'),
            self::Sent => __('versendet'),
            self::Responded => __('beantwortet'),
            self::Expired => __('abgelaufen'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Created, self::Sent => [self::Responded],
            self::Responded, self::Expired => [],
        };
    }
}
