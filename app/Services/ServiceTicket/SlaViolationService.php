<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaViolationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\SlaViolationKind;
use App\Models\{ServiceTicket, SlaViolation, User};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Erkennung und Pflege des SLA-Verletzungsregisters (Feature 010).
 *
 * Legt je Ticket + Verletzungstyp genau EINE {@see SlaViolation} an
 * (Unique-Key sla_violations_uniq_ticket_kind). Die Erkennung ist idempotent:
 * mehrfache Läufe (Scanner, Statusübergänge) erzeugen keine Duplikate.
 */
class SlaViolationService {
    /**
     * Reaktionsverletzung festhalten: die maßgebliche Frist (reaction_due_at)
     * wurde überschritten, ohne dass die erste Reaktion (acknowledged_at)
     * rechtzeitig erfolgte. Liefert die (neue oder bestehende) Violation oder
     * null, wenn keine Frist hinterlegt ist.
     */
    public function recordResponseBreach(ServiceTicket $ticket, ?Carbon $detectedAt = null): ?SlaViolation {
        if ($ticket->reaction_due_at === null) {
            return null;
        }
        $reference = $ticket->acknowledged_at ?? $detectedAt ?? Carbon::now();

        return $this->record($ticket, SlaViolationKind::ResponseTime, $ticket->reaction_due_at, $reference);
    }

    /**
     * Lösungsverletzung festhalten: die maßgebliche Frist (resolution_due_at)
     * wurde überschritten, ohne dass die Lösung (resolved_at) rechtzeitig
     * erfolgte.
     */
    public function recordResolutionBreach(ServiceTicket $ticket, ?Carbon $detectedAt = null): ?SlaViolation {
        if ($ticket->resolution_due_at === null) {
            return null;
        }
        $reference = $ticket->resolved_at ?? $detectedAt ?? Carbon::now();

        return $this->record($ticket, SlaViolationKind::ResolutionTime, $ticket->resolution_due_at, $reference);
    }

    /**
     * Idempotenter Schreibvorgang: liefert eine vorhandene Violation unverändert
     * zurück, sonst wird sie angelegt. Ein paralleler Lauf, der den Unique-Key
     * verletzt, fällt sauber auf den bestehenden Eintrag zurück.
     */
    private function record(ServiceTicket $ticket, SlaViolationKind $kind, Carbon $targetAt, Carbon $breachedAt): SlaViolation {
        $existing = SlaViolation::query()
            ->where('service_ticket_id', $ticket->id)
            ->where('kind', $kind->value)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $overdue = max(0, (int) round($targetAt->diffInMinutes($breachedAt, false)));

        try {
            $violation = new SlaViolation([
                'organization_id' => $ticket->organization_id,
                'service_ticket_id' => $ticket->id,
                'sla_contract_id' => $ticket->sla_contract_id,
                'kind' => $kind->value,
                'target_at' => $targetAt,
                'breached_at' => $breachedAt,
                'overdue_minutes' => $overdue,
                'priority' => $ticket->priority->value,
            ]);
            $violation->save();
        } catch (Throwable) {
            // Unique-Key (Ticket+Typ) verletzt → ein paralleler Lauf war schneller.
            return SlaViolation::query()
                ->where('service_ticket_id', $ticket->id)
                ->where('kind', $kind->value)
                ->firstOrFail();
        }

        $violation->audit('sla_violation.detected', [
            'kind' => $kind->value,
            'overdue_minutes' => $overdue,
            'target_at' => $targetAt->toIso8601String(),
        ]);

        return $violation;
    }

    /**
     * Verletzung quittieren (Sichtung dokumentiert). Idempotent: bereits
     * quittierte Einträge bleiben unverändert.
     */
    public function acknowledge(SlaViolation $violation, User $actor, ?string $cause = null): SlaViolation {
        if ($violation->acknowledged_at !== null) {
            return $violation;
        }

        $violation->acknowledged_at = Carbon::now();
        $violation->acknowledged_by = $actor->id;
        if ($cause !== null && trim($cause) !== '') {
            $violation->cause = mb_substr(trim($cause), 0, 191);
        }
        $violation->save();

        $violation->audit('sla_violation.acknowledged', [
            'by' => $actor->id,
            'cause' => $violation->cause,
        ]);

        return $violation;
    }
}
