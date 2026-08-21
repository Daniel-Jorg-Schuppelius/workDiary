<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art des Sicherheitseinbehalts (Feature 113, MVP-602).
 *
 * Die Unterscheidung ist nicht kosmetisch: Der Vertragserfüllungseinbehalt
 * endet mit der Abnahme, der Gewährleistungseinbehalt beginnt dort erst.
 * Wer beide gleich behandelt, gibt entweder zu früh oder zu spät frei.
 */
enum RetentionKind: string implements HasLabel {
    use HasOptions;

    case Warranty = 'warranty';
    case Performance = 'performance';

    public function label(): string {
        return (string) __('enums.retention_kind.' . $this->value);
    }
}
