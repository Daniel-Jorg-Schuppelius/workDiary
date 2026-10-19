<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingPartyLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;

/**
 * Kontaktsuche im Buchhaltungsprogramm (Phase 152, MVP-1226): ein Plugin
 * sucht nach E-Mail und Name und liefert rohe Kandidaten; ob einer zum Beleg
 * passt, entscheidet der Kern über die Kontaktidentität. Plugins tragen sich
 * beim Booten per `ModuleRegistry::contribute()` ein.
 */
interface IncomingPartyLookup {
    /** Plugin-Kennung, zugleich Schlüssel der Kandidaten. */
    public function key(): string;

    public function label(): string;

    public function isEnabled(Organization $organization): bool;

    /** @return list<IncomingPartyCandidate> ungeprüfte Treffer */
    public function lookup(IncomingEInvoice $incoming): array;
}
