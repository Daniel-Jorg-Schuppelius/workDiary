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

use App\Enums\ServiceTicket\{ServiceTicketPriority, SlaStatus};
use App\Models\{ServiceTicket, SlaContract};
use Illuminate\Support\Carbon;

class SlaTimer {
    /**
     * Anteil der Restzeit (relativ zur Gesamtfrist), unter dem ein Ticket als
     * „gefährdet" (atRisk) gilt. Spiegelt die Eskalationsschwelle sla.atRisk.
     */
    public const AT_RISK_FRACTION = 0.20;

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

    /**
     * Abgeleiteter Lösungs-SLA-Status eines Tickets (reine Anzeige). Bezieht
     * sich auf die Lösungsfrist (resolution_due_at) als maßgebliche SLA-Frist.
     */
    public function resolutionStatus(ServiceTicket $ticket, ?Carbon $now = null): SlaStatus {
        return $this->statusFor(
            due: $ticket->resolution_due_at,
            start: $ticket->reported_at,
            completed: $ticket->resolved_at,
            breachedFlag: (bool) $ticket->resolution_breached,
            now: $now,
        );
    }

    /**
     * Abgeleiteter Reaktions-SLA-Status eines Tickets (reine Anzeige). Bezieht
     * sich auf die Reaktionsfrist (reaction_due_at); als „erfüllt" gilt sie ab
     * der ersten Bestätigung (acknowledged_at).
     */
    public function reactionStatus(ServiceTicket $ticket, ?Carbon $now = null): SlaStatus {
        return $this->statusFor(
            due: $ticket->reaction_due_at,
            start: $ticket->reported_at,
            completed: $ticket->acknowledged_at,
            breachedFlag: (bool) $ticket->reaction_breached,
            now: $now,
        );
    }

    /**
     * Verbleibende Minuten bis zur Frist (negativ = überfällig); null ohne Frist.
     */
    public function minutesRemaining(?Carbon $due, ?Carbon $now = null): ?int {
        if ($due === null) {
            return null;
        }
        $now ??= Carbon::now();

        return (int) round($now->diffInMinutes($due, false));
    }

    /**
     * Kernlogik der Statusableitung: erst der erledigte/markierte Endzustand,
     * dann die Restzeit gegen die at-risk-Schwelle.
     */
    private function statusFor(?Carbon $due, ?Carbon $start, ?Carbon $completed, bool $breachedFlag, ?Carbon $now): SlaStatus {
        if ($due === null) {
            return SlaStatus::None;
        }
        $now ??= Carbon::now();

        // Abgeschlossen: erfüllt, wenn rechtzeitig, sonst verletzt.
        if ($completed !== null) {
            return $completed->lessThanOrEqualTo($due) ? SlaStatus::Met : SlaStatus::Breached;
        }

        // Offen: ein gesetztes Breach-Flag oder eine überschrittene Frist ⇒ verletzt.
        if ($breachedFlag || $now->greaterThan($due)) {
            return SlaStatus::Breached;
        }

        // Restzeit relativ zur Gesamtfrist gegen die Schwelle.
        $remaining = $now->diffInSeconds($due, false);
        if ($start !== null) {
            $total = $start->diffInSeconds($due, false);
            if ($total > 0 && ($remaining / $total) <= self::AT_RISK_FRACTION) {
                return SlaStatus::AtRisk;
            }
        }

        return SlaStatus::OnTrack;
    }
}
