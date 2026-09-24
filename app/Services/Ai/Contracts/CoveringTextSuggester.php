<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoveringTextSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;

/** Anschreiben-/Mahntexte und Portal-Antwortübersetzung per KI; Null-Bindung wirft AiUnavailableException. */
interface CoveringTextSuggester {
    public const CAPABILITY_MAIL_TEXT = 'invoicing.mail_text';

    public const CAPABILITY_DUNNING_TEXT = 'invoicing.dunning_text';

    public const CAPABILITY_ANSWER_TRANSLATE = 'portal.answer_translate';

    public function suggestMailText(Invoice $invoice): string;

    public function suggestDunningText(Invoice $invoice, int $level): string;

    public function translatePortalAnswer(Organization $organization, ?int $customerId, string $text, string $targetLanguage): string;
}
