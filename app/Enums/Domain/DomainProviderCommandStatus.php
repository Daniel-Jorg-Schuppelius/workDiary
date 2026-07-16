<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderCommandStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand eines schreibenden Provider-Befehls in der Command-Outbox
 * (`domain_provider_commands`, Feature 083, MVP-390/391).
 *
 *  - `Draft`      — angelegt, noch nicht freigegeben (Vier-Augen offen);
 *  - `Approved`   — freigegeben, wartet auf Dispatch;
 *  - `Pending`    — an den Provider gesendet, Bestätigung ausstehend;
 *  - `Confirmed`  — Provider hat mit vollständigem `EOF` bestätigt;
 *  - `Failed`     — Provider hat einen Fehlercode geliefert;
 *  - `Unknown`    — kein `EOF`/Timeout: Ausgang unklar, NIE blind wiederholen;
 *  - `Conflict`   — Nachabgleich zeigt abweichenden Providerzustand.
 */
enum DomainProviderCommandStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case Conflict = 'conflict';

    public function label(): string {
        return (string) __('enums.domain.command_status.' . $this->value);
    }

    /** Endzustände lösen keinen weiteren Dispatch aus. */
    public function isTerminal(): bool {
        return match ($this) {
            self::Confirmed, self::Failed => true,
            default => false,
        };
    }

    /** Ein unklarer Ausgang muss reconciled statt wiederholt werden. */
    public function needsReconciliation(): bool {
        return $this === self::Unknown || $this === self::Conflict;
    }

    public function badge(): string {
        return match ($this) {
            self::Confirmed => 'success',
            self::Approved, self::Pending => 'info',
            self::Draft => 'neutral',
            self::Failed, self::Unknown, self::Conflict => 'error',
        };
    }
}
