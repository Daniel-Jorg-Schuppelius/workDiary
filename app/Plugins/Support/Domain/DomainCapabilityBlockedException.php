<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCapabilityBlockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use RuntimeException;

/**
 * Wird geworfen, wenn ein Befehl einen Fähigkeitsbereich nutzt, der im realen
 * Konto nicht belegt ist (Feature 083). Die UI übersetzt das in einen
 * erklärten Blocked-State, nie in einen scheinbar funktionierenden Button.
 */
class DomainCapabilityBlockedException extends RuntimeException {
    public function __construct(public readonly DomainCapabilityArea $area) {
        parent::__construct(sprintf('DomainReselling-Fähigkeit "%s" ist im Konto nicht belegt.', $area->value));
    }
}
