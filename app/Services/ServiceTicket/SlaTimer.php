<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaTimer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketPriority;
use App\Models\SlaContract;
use Illuminate\Support\Carbon;

class SlaTimer {
    /**
     * @return array{reaction_due_at: Carbon|null, resolution_due_at: Carbon|null}
     */
    public function computeDeadlines(SlaContract $contract, ServiceTicketPriority $priority, Carbon $reportedAt): array {
        /** @var array<string, array{reaction_minutes?: int, resolution_minutes?: int}> $table */
        $table = $contract->priority_table;
        $entry = $table[$priority->value] ?? null;
        if ($entry === null) {
            return ['reaction_due_at' => null, 'resolution_due_at' => null];
        }

        $reaction = isset($entry['reaction_minutes']) ? (int) $entry['reaction_minutes'] : null;
        $resolution = isset($entry['resolution_minutes']) ? (int) $entry['resolution_minutes'] : null;

        return [
            'reaction_due_at' => $reaction !== null ? $reportedAt->copy()->addMinutes($reaction) : null,
            'resolution_due_at' => $resolution !== null ? $reportedAt->copy()->addMinutes($resolution) : null,
        ];
    }

    /**
     * Findet den passenden SLA-Vertrag: zuerst Customer-spezifisch, sonst Default.
     */
    public function resolveContract(int $organizationId, ?int $customerId): ?SlaContract {
        if ($customerId !== null) {
            $specific = SlaContract::query()
                ->where('organization_id', $organizationId)
                ->where('customer_id', $customerId)
                ->where('is_active', true)
                ->first();
            if ($specific !== null) {
                return $specific;
            }
        }

        return SlaContract::query()
            ->where('organization_id', $organizationId)
            ->whereNull('customer_id')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
