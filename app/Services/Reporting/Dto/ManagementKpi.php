<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementKpi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Dto;

/** Eine Kennzahl der Leitungssicht (Feature 184): Wert fertig formatiert, Link in die Fachauswertung. */
final readonly class ManagementKpi {
    public function __construct(
        public string $label,
        public string $value,
        public ?string $hint = null,
        public string $tone = 'neutral',
        public ?string $url = null,
    ) {}
}
