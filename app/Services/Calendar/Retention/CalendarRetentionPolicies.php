<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Retention;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Models\Calendar\AppointmentRequest;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/**
 * Entschiedene Terminanfragen (Kontaktdaten der Anfragenden) nach der Frist
 * des Bereichs (Vollscan 2026-09-15, P8-24); offene Anfragen bleiben.
 */
final class CalendarRetentionPolicies implements RetentionPolicyProvider {
    /** @return list<RetentionPolicy> */
    public function policies(): array {
        return [
            new RetentionPolicy(
                area: 'appointment_requests',
                modelClass: AppointmentRequest::class,
                overdueQuery: fn ($organization, $cutoff) => AppointmentRequest::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('status', '!=', AppointmentRequestStatus::Requested->value)
                    ->where(static fn ($query) => $query->where('decided_at', '<', $cutoff)
                        ->orWhere(static fn ($q) => $q->whereNull('decided_at')->where('created_at', '<', $cutoff))),
            ),
        ];
    }
}
