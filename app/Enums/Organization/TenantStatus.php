<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Organization;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * SaaS-Mandantenstatus einer Organisation (Feature 021).
 *
 * Wird in `organizations.tenant_status` gespeichert. Ist die Spalte NULL,
 * leitet {@see \App\Models\Organization::tenantStatus()} den Status aus
 * Testphase ({@see \App\Models\Organization::$trial_ends_at}), Aktiv-Flag
 * und dem Lizenz-Ablauf (inkl. Grace-Period) ab.
 */
enum TenantStatus: string implements HasLabel {
    use HasOptions;

    /** Testphase – Schreibzugriff erlaubt, Hinweisbanner. */
    case Trial = 'trial';

    /** Regulär aktiv. */
    case Active = 'active';

    /** Vom Plattform-Admin gesperrt – Schreibzugriff blockiert (423). */
    case Suspended = 'suspended';

    /** Lizenz endgültig abgelaufen (abgeleitet, nicht setzbar). */
    case Expired = 'expired';

    public function label(): string {
        return (string) __('tenant.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Trial => 'info',
            self::Suspended => 'error',
            self::Expired => 'error',
        };
    }

    /** Sperrt dieser Status schreibende Aktionen? */
    public function blocksWrites(): bool {
        return $this === self::Suspended || $this === self::Expired;
    }

    /**
     * Vom Plattform-Admin manuell setzbare Werte. `expired` wird ausschließlich
     * aus dem Lizenz-Ablauf abgeleitet und ist daher nicht wählbar.
     *
     * @return list<self>
     */
    public static function assignable(): array {
        return [self::Trial, self::Active, self::Suspended];
    }
}
