<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullSearchAnswerSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Ai\AiTextSuggestion;
use App\Models\Platform\{Organization, User};
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Search\ActivitySearchCriteria;

final class NullSearchAnswerSuggester implements SearchAnswerSuggester {
    public function answer(User $user, Organization $organization, ActivitySearchCriteria $criteria, ?int $connectionId = null): AiTextSuggestion {
        throw AiUnavailableException::moduleInactive();
    }
}
