<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainEventStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Domain;

/** Stand eines Provider-Ereignisses im Durable-Store (Feature 083, MVP-391): erst gespeichert, dann beim Anbieter quittiert. */
enum DomainEventStatus: string {
    case Stored = 'stored';
    case Acknowledged = 'acknowledged';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben — eine gescheiterte Quittung bleibt gespeichert. */
    case Failed = 'failed';
}
