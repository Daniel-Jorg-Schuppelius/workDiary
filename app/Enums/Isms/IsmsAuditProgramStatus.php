<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditProgramStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand eines Auditprogramms (Nachtrag 044d). Ohne Übergangstabelle: die Statuspflege setzt jeden Stand aus jedem. */
enum IsmsAuditProgramStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Active => (string) __('enums.isms.isms_audit_program_status.active'),
            self::Completed => (string) __('enums.isms.isms_audit_program_status.completed'),
            self::Cancelled => (string) __('enums.isms.isms_audit_program_status.cancelled'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Completed => 'info',
            self::Cancelled => 'neutral',
        };
    }
}
