<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeMessageKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Customer;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Nachricht am Kundeneingang (MVP-1075): Rückfrage des Betriebs, Antwort des Kunden, interne Notiz. */
enum IntakeMessageKind: string implements HasLabel {
    use HasOptions;

    case Question = 'question';
    case Reply = 'reply';
    case Note = 'note';

    public function label(): string {
        return (string) __('customer_intake.message_kind.' . $this->value);
    }

    /** Interne Notizen und ihre Dateien sieht der Kunde nie. */
    public function isCustomerVisible(): bool {
        return $this !== self::Note;
    }
}
