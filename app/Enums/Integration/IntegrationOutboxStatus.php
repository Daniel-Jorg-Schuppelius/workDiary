<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines generischen Outbox-Eintrags (Feature 055, MVP-114) — analog
 * {@see \App\Enums\Inventory\OutboxStatus}: terminale Fehlschläge werden
 * fachlich kompensiert (Inbox-Fall), nie per DB-Rollback.
 */
enum IntegrationOutboxStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case CompensationRequired = 'compensation_required';

    public function isTerminal(): bool {
        return in_array($this, [self::Confirmed, self::CompensationRequired], true);
    }

    public function label(): string {
        return __('integration.outbox.status.' . $this->value);
    }
}
