<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OutboxStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Zustellstatus eines Outbox-Eintrags zur externen Bestandsführung
 * (Feature 048, MVP-072). „compensation_required" = die externe Anwendung der
 * lokal bereits gebuchten Bewegung ist endgültig fehlgeschlagen und muss
 * fachlich ausgeglichen werden (Gegenbuchung), niemals per DB-Rollback.
 */
enum OutboxStatus: string {
    case Pending = 'pending';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case CompensationRequired = 'compensation_required';

    public function label(): string {
        return __('inventory.outbox.status.' . $this->value);
    }

    public function isTerminal(): bool {
        return $this === self::Confirmed || $this === self::CompensationRequired;
    }
}
