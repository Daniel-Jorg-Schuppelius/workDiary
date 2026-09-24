<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullQuestionTicketOpener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Contracts;

use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\ServiceTicket;

final class NullQuestionTicketOpener implements QuestionTicketOpener {
    public function open(Organization $organization, User $learner, array $attributes, User $assignee): ?ServiceTicket {
        return null;
    }
}
