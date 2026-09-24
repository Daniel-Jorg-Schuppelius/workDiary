<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalQuerySuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Ai\AiTextSuggestion;
use App\Models\Customer\CustomerQuery;
use App\Models\Platform\User;

/** Kundenanfrage per KI verstehen (Portal); Null-Bindung wirft AiUnavailableException. */
interface PortalQuerySuggester {
    public const CAPABILITY = 'portal.query_understand';

    public function understand(CustomerQuery $query, ?User $user, ?int $connectionId = null): AiTextSuggestion;
}
