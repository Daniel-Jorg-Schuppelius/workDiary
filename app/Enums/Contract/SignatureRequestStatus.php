<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignatureRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Unterschriftsanforderung (Feature 157). „Nachweis
 * eingegangen"/„abgelehnt" gehören hierher, nicht zur Fassung.
 */
enum SignatureRequestStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case EvidenceReceived = 'evidence_received';
    case EvidenceRejected = 'evidence_rejected';
    case Signed = 'signed';
    case Waived = 'waived';

    public function label(): string {
        return (string) __('contract-signing.request_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'ghost',
            self::EvidenceReceived => 'info',
            self::EvidenceRejected => 'warning',
            self::Signed => 'success',
            self::Waived => 'neutral',
        };
    }

    /** Anforderung zählt als erfüllt. */
    public function isFulfilled(): bool {
        return in_array($this, [self::Signed, self::Waived], true);
    }

    /** Eine neue Unterschrift/ein neuer Nachweis darf eingehen. */
    public function acceptsSubmission(): bool {
        return in_array($this, [self::Pending, self::EvidenceRejected], true);
    }
}
