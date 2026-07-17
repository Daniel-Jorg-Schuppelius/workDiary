<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Licensing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fachlicher Zustand eines Moduls für eine Organisation (MVP-052 §2).
 *
 * Trennt bewusst die Lizenzquelle von der lokalen Deaktivierung, damit die
 * Oberfläche und die serverseitigen Sperrmeldungen unterscheidbar bleiben.
 * Ein einzelnes boolesches „enabled" reicht dafür nicht.
 */
enum ModuleStatus: string implements HasLabel {
    use HasOptions;

    /** Nicht von Plan, Lizenz oder Add-on umfasst; nicht aktivierbar. */
    case NotLicensed = 'notLicensed';

    /** Lizenziert und von der Organisation aktiviert. */
    case Active = 'active';

    /** Lizenziert, aber vom Org-Admin bewusst deaktiviert. */
    case InactiveByCustomer = 'inactiveByCustomer';

    /** Lizenziert, aber durch Lizenz-/Mandanten-/Systemstatus temporär gesperrt. */
    case Blocked = 'blocked';

    public function label(): string {
        return match ($this) {
            self::NotLicensed => __('Nicht lizenziert'),
            self::Active => __('Aktiv'),
            self::InactiveByCustomer => __('Deaktiviert'),
            self::Blocked => __('Gesperrt'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::InactiveByCustomer => 'neutral',
            self::Blocked => 'warning',
            self::NotLicensed => 'ghost',
        };
    }

    /** Effektiv verfügbar (Modul darf benutzt werden)? */
    public function isAvailable(): bool {
        return $this === self::Active;
    }

    /** Lizenziert (unabhängig von lokaler Deaktivierung)? */
    public function isLicensed(): bool {
        return $this !== self::NotLicensed;
    }

    /** Kann der Org-Admin diesen Zustand per Schalter ändern? */
    public function isConfigurable(): bool {
        return $this === self::Active || $this === self::InactiveByCustomer;
    }
}
