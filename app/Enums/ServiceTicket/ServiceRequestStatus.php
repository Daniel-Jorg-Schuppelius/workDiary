<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Service-Requests (Feature 065, MVP-154): Genehmigung, dann Erfüllung. */
enum ServiceRequestStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben; die Erfüllung lässt ihn als Ausgang zu. */
    case Fulfilling = 'fulfilling';
    case Done = 'done';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('enums.service_ticket.service_request_status.draft'),
            self::PendingApproval => (string) __('enums.service_ticket.service_request_status.pending_approval'),
            self::Approved => (string) __('enums.service_ticket.service_request_status.approved'),
            self::Rejected => (string) __('enums.service_ticket.service_request_status.rejected'),
            self::Fulfilling => (string) __('enums.service_ticket.service_request_status.fulfilling'),
            self::Done => (string) __('enums.service_ticket.service_request_status.done'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::PendingApproval => [self::Approved, self::Rejected],
            self::Approved, self::Fulfilling => [self::Done],
            self::Draft, self::Rejected, self::Done => [],
        };
    }
}
