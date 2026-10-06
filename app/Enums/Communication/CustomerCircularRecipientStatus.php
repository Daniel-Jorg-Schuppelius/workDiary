<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularRecipientStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zustellnachweis je Empfänger eines Rundschreibens (Feature 119, MVP-608). */
enum CustomerCircularRecipientStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('circular.recipient_status.' . $this->value);
    }
}
