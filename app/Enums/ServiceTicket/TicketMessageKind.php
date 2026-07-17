<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketMessageKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Nachrichtentyp (Feature 065, MVP-152) — Typfrage, keine Flagfrage:
 * öffentlich vs. intern ist technisch unverwechselbar; NUR public_reply
 * darf die Versand-Pipeline betreten.
 */
enum TicketMessageKind: string implements HasLabel {
    use HasOptions;

    case PublicReply = 'public_reply';
    case InternalNote = 'internal_note';
    case SystemEvent = 'system_event';

    public function label(): string {
        return match ($this) {
            self::PublicReply => (string) __('Antwort'),
            self::InternalNote => (string) __('Interne Notiz'),
            self::SystemEvent => (string) __('Systemereignis'),
        };
    }

    public function isCustomerVisible(): bool {
        return $this === self::PublicReply;
    }
}
