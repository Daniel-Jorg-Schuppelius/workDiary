<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullCoveringTextSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Services\Ai\Exceptions\AiUnavailableException;

final class NullCoveringTextSuggester implements CoveringTextSuggester {
    public function suggestMailText(Invoice $invoice): string {
        throw AiUnavailableException::moduleInactive();
    }

    public function suggestDunningText(Invoice $invoice, int $level): string {
        throw AiUnavailableException::moduleInactive();
    }

    public function translatePortalAnswer(Organization $organization, ?int $customerId, string $text, string $targetLanguage): string {
        throw AiUnavailableException::moduleInactive();
    }
}
