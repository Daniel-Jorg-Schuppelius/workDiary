<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullIssueTicketOpener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Contracts;

use App\Models\Diary\OpenIssue;
use App\Models\Platform\User;

final class NullIssueTicketOpener implements IssueTicketOpener {
    public function available(User $actor): bool {
        return false;
    }

    public function open(OpenIssue $issue, User $actor): ?array {
        return null;
    }

    public function linksFor(array $issueIds, User $viewer): array {
        return [];
    }
}
