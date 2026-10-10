<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRecourseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Status des Lieferanten-/Herstellerregresses (MVP-253). */
enum ClaimRecourseStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('enums.claims.claim_recourse_status.draft'),
            self::Submitted => (string) __('enums.claims.claim_recourse_status.submitted'),
            self::Accepted => (string) __('enums.claims.claim_recourse_status.accepted'),
            self::PartiallyAccepted => (string) __('enums.claims.claim_recourse_status.partially_accepted'),
            self::Rejected => (string) __('enums.claims.claim_recourse_status.rejected'),
            self::Closed => (string) __('enums.claims.claim_recourse_status.closed'),
        };
    }
}
