<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentDispatchStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Contracts\HasLabel;

/**
 * Technischer Stand eines Zustellversuchs (Feature 066, MVP-168) — vom
 * fachlichen Empfang getrennt. Die App setzt ihn selbst; den Anbieterstatus
 * eines Peppol-Versands trägt `meta.transport_status`.
 */
enum DocumentDispatchStatus: string implements HasLabel {
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => 'error',
            self::Queued => 'ghost',
        };
    }
}
