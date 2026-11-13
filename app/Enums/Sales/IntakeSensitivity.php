<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeSensitivity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\User\Permission;

/**
 * Enge Sichtbarkeit in der Aufnahmeakte (Feature 200): Ausweisangaben und
 * Ausweiskopie (§ 8 Abs. 2 GwG) sowie Gesundheitsangaben (Art. 9 DSGVO)
 * erben nicht die Sichtbarkeit von Lead oder Kunde, sondern brauchen je ein
 * eigenes Recht — in Bogen, Anhängen, PDF und Auskunft. Gespeichert werden
 * sie verschlüsselt (`lead_intakes.sensitive_values`).
 */
enum IntakeSensitivity: string implements HasLabel {
    use HasOptions;

    case Identity = 'identity';
    case Health = 'health';

    public function label(): string {
        return (string) __('enums.sales.intake_sensitivity.' . $this->value);
    }

    /** Recht, das die Angaben dieser Art zeigt und schreibt. */
    public function permission(): Permission {
        return match ($this) {
            self::Identity => Permission::LeadIntakeIdentity,
            self::Health => Permission::LeadIntakeHealth,
        };
    }
}
