<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullItemTextSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Ai\AiTextSuggestion;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\Platform\User;
use App\Services\Ai\Exceptions\AiUnavailableException;

final class NullItemTextSuggester implements ItemTextSuggester {
    public function suggestForTransferPosition(BillingTransfer $transfer, BillingTransferPosition $position, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        throw AiUnavailableException::moduleInactive();
    }
}
