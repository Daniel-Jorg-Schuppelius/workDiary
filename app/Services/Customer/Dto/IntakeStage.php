<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeStage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Dto;

/**
 * Kundensicht eines Eingangs (MVP-1075): verständlicher Stand und der nächste
 * Schritt des Kunden. Vor der Übernahme aus Eingang und Angebot abgeleitet,
 * danach vom Zieladapter aus der Fachakte.
 */
final readonly class IntakeStage {
    public function __construct(
        public string $label,
        public string $tone = 'neutral',
        public ?string $nextStep = null,
        public bool $actionRequired = false,
    ) {}
}
