<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Audits (Feature 046, Inkrement C): internes Audit (eigenes
 * Auditprogramm), externes Audit (Zertifizierungs-/Überwachungsaudit)
 * oder Lieferantenaudit.
 */
enum AuditKind: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case External = 'external';
    case Supplier = 'supplier';

    public function label(): string {
        return (string) __('enums.isms.audit-kind.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Internal => 'info',
            self::External => 'primary',
            self::Supplier => 'warning',
        };
    }
}
