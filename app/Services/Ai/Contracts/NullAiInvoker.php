<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullAiInvoker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Platform\Organization;
use App\Services\Ai\Dto\AiInvocationResult;
use App\Services\Ai\Exceptions\AiUnavailableException;

final class NullAiInvoker implements AiInvoker {
    public function invoke(Organization $organization, string $capabilityKey, AiRequestInterface $request, ?int $requestedConnectionId = null): AiInvocationResult {
        throw AiUnavailableException::moduleInactive();
    }
}
