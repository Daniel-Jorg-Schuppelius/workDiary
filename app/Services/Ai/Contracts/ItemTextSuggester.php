<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ItemTextSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Ai\AiTextSuggestion;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\Platform\User;

/** Positionstexte per KI (Feature Rechnungs-/Übergabepositionen); Null-Bindung wirft AiUnavailableException. */
interface ItemTextSuggester {
    public const CAPABILITY_ITEM = 'invoicing.item_text';

    public const CAPABILITY_BLOCK = 'invoicing.block_text';

    public const CAPABILITY_TRANSLATE = 'invoicing.item_translate';

    public const CAPABILITY_QUOTE_ITEM = 'quotes.item_text';

    public function suggestForTransferPosition(BillingTransfer $transfer, BillingTransferPosition $position, ?User $user, ?int $connectionId = null): AiTextSuggestion;
}
