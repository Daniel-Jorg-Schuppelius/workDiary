<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceLevelResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Contracts;

use App\Models\ServiceTicket\SlaContract;

/**
 * SLA-Vertrag zu Organisation/Kunde/Projekt (MVP-863): definiert vom
 * Anlagenkern (Dossier), gebunden vom Helpdesk ({@see \App\Services\ServiceTicket\SlaTimer}).
 * Null-Bindung: kein Vertrag.
 */
interface ServiceLevelResolver {
    public function resolveContract(int $organizationId, ?int $customerId, ?int $projectId = null): ?SlaContract;
}
