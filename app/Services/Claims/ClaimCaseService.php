<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimCaseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims;

use App\Enums\Claims\{ClaimKind, ClaimStatus, ClaimVerdict};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Numbering\NumberScope;
use App\Models\Claims\{ClaimAssessment, ClaimCase, ClaimDecision};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\{Organization, User};
use App\Notifications\GenericEventNotification;
use App\Services\Inventory\SerialService;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reklamationsakten (Feature 072, MVP-247–249): Eröffnung mit Nummern-
 * kreis + Dublettenprüfung, Bewertung mit P2-Snapshot (Seriennummern-/
 * Fristenfakten zum Zeitpunkt), Entscheidung mit Pflichtbegründung,
 * Statusübergänge und Fristeneskalation (MVP-255). Keine automatische
 * Anspruchsentscheidung — jede Entscheidung ist eine Nutzeraktion.
 */
class ClaimCaseService {
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly SerialService $serials,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function open(Organization $organization, User $creator, array $attributes): ClaimCase {
        return DB::transaction(function () use ($organization, $creator, $attributes): ClaimCase {
            $reportedAt = $attributes['reported_at'] ?? now();

            return ClaimCase::query()->create(array_merge($attributes, [
                'organization_id' => $organization->id,
                'number' => $this->numbers->next($organization, NumberScope::Claim),
                'status' => ClaimStatus::Received->value,
                'reported_at' => $reportedAt,
                'due_at' => $attributes['due_at'] ?? now()->addDays((int) config('claims.default_due_days', 14)),
                'created_by' => $creator->id,
            ]));
        });
    }

    /**
     * Dublettenprüfung (MVP-248): offene Fälle desselben Kunden mit
     * demselben betroffenen Objekt (Auftrag/Rechnung/Asset/Seriennummer).
     *
     * @param array<string, mixed> $attributes
     * @return Collection<int, ClaimCase>
     */
    public function duplicates(array $attributes, ?int $ignoreId = null): Collection {
        $query = ClaimCase::query()->open()->when($ignoreId !== null, fn($q) => $q->whereKeyNot($ignoreId));

        $query->where(function ($q) use ($attributes): void {
            $matched = false;
            foreach (['diary_entry_id', 'invoice_id', 'asset_id', 'stock_serial_id'] as $column) {
                $value = $attributes[$column] ?? null;
                if ($value !== null && $value !== '') {
                    $q->orWhere($column, (int) $value);
                    $matched = true;
                }
            }
            $serial = trim((string) ($attributes['serial_no'] ?? ''));
            if ($serial !== '') {
                $q->orWhere('serial_no', $serial);
                $matched = true;
            }
            if (! $matched) {
                // Ohne Objektbezug: gleicher Kunde + gleicher Titel.
                $q->where('customer_id', (int) ($attributes['customer_id'] ?? 0))
                    ->where('title', (string) ($attributes['title'] ?? ''));
            }
        });

        $customerId = $attributes['customer_id'] ?? null;
        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', (int) $customerId);
        }

        return $query->limit(5)->get();
    }

    /**
     * Bewertung (MVP-249): Anspruchsart + Ergebnis mit Pflichtbegründung;
     * vorherige aktive Bewertungen werden abgelöst (superseded). Der
     * Snapshot friert die Faktenlage ein (P2) — inkl. Seriennummernprüfung
     * „wurde je an diesen Kunden geliefert?" (keine Auto-Entscheidung,
     * nur Nachweis).
     */
    public function assess(ClaimCase $case, User $assessor, ClaimKind $kind, ClaimVerdict $verdict, string $justification): ClaimAssessment {
        return DB::transaction(function () use ($case, $assessor, $kind, $verdict, $justification): ClaimAssessment {
            $case->assessments()->where('status', 'active')->update(['status' => 'superseded']);

            $serialShipped = null;
            $serial = trim((string) ($case->serial_no ?? ''));
            if ($serial !== '' && $case->customer !== null) {
                $serialShipped = $this->serials->wasShippedTo((int) $case->organization_id, $serial, $case->customer);
            }

            $assessment = $case->assessments()->create([
                'organization_id' => $case->organization_id,
                'claim_kind' => $kind->value,
                'verdict' => $verdict->value,
                'justification' => $justification,
                'snapshot' => [
                    'assessed_on' => now()->toDateTimeString(),
                    'reported_at' => $case->reported_at->toDateTimeString(),
                    'is_b2b' => $case->is_b2b,
                    // § 377 HGB: Rügedatum zum Bewertungszeitpunkt (B2B).
                    'complaint_notice_at' => $case->complaint_notice_at?->toDateString(),
                    'serial_no' => $serial !== '' ? $serial : null,
                    'serial_shipped_to_customer' => $serialShipped,
                    'invoice_number' => $case->invoice?->number,
                    'invoice_issued_on' => $case->invoice?->issued_on?->toDateString(),
                ],
                'status' => 'active',
                'assessed_by' => $assessor->id,
                'assessed_at' => now(),
            ]);

            if ($case->status === ClaimStatus::Received) {
                $case->forceFill(['status' => ClaimStatus::Assessing->value])->save();
            }

            return $assessment;
        });
    }

    /**
     * Entscheidung (MVP-249): braucht eine aktive Bewertung und eine
     * Pflichtbegründung; der Snapshot kopiert die Bewertungsfakten
     * (Auditspur — spätere Änderungen deuten den Fall nicht um).
     */
    public function decide(ClaimCase $case, User $decider, string $decision, string $justification): ClaimDecision {
        if (! in_array($decision, ClaimDecision::DECISIONS, true)) {
            throw new \InvalidArgumentException('Unbekannte Entscheidung: ' . $decision);
        }
        $assessment = $case->activeAssessment();
        if ($assessment === null) {
            throw new \RuntimeException((string) __('Ohne aktive Bewertung kann nicht entschieden werden.'));
        }

        return DB::transaction(function () use ($case, $decider, $decision, $justification, $assessment): ClaimDecision {
            $record = $case->decisions()->create([
                'organization_id' => $case->organization_id,
                'decision' => $decision,
                'justification' => $justification,
                'snapshot' => [
                    'claim_kind' => $assessment->claim_kind->value,
                    'verdict' => $assessment->verdict->value,
                    'assessment_justification' => $assessment->justification,
                    'assessment_snapshot' => $assessment->snapshot,
                ],
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ]);

            $target = $decision === 'rejected' ? ClaimStatus::Rejected : ClaimStatus::Decided;
            $this->transition($case, $target);
            $case->forceFill(['decided_at' => now()])->save();

            return $record;
        });
    }

    /** Statuswechsel mit Übergangsprüfung (MVP-246). */
    public function transition(ClaimCase $case, ClaimStatus $target): ClaimCase {
        if (! in_array($target, $case->status->allowedTransitions(), true)) {
            throw new \RuntimeException((string) __('Statuswechsel :from → :to ist nicht erlaubt.', [
                'from' => $case->status->label(),
                'to' => $target->label(),
            ]));
        }
        $case->forceFill(['status' => $target->value])->save();

        return $case;
    }

    public function close(ClaimCase $case, User $actor): ClaimCase {
        $this->transition($case, ClaimStatus::Closed);
        $case->forceFill(['closed_at' => now(), 'closed_by' => $actor->id])->save();

        return $case;
    }

    /**
     * Fristeneskalation (MVP-255): überfällige offene Fälle melden —
     * einmal je Fall/Tag (Nachweis über notification_dispatch_log),
     * keine stillen Liegezeiten.
     */
    public function escalateOverdue(Organization $organization): int {
        $count = 0;
        $cases = ClaimCase::query()
            ->where('organization_id', $organization->id)
            ->overdue()
            ->with('responsible')
            ->get();

        foreach ($cases as $case) {
            $stage = 'overdue:' . now()->toDateString();
            $log = NotificationDispatchLog::query()->firstOrCreate([
                'organization_id' => $case->organization_id,
                'event' => NotificationEvent::ClaimEscalation->value,
                'subject_type' => $case->getMorphClass(),
                'subject_id' => $case->getKey(),
                'stage' => $stage,
            ], ['recipient_count' => 0]);
            if ($log->recipient_count > 0) {
                continue; // heute bereits eskaliert
            }

            $recipient = $case->responsible;
            if ($recipient === null) {
                continue;
            }
            $recipient->notify(new GenericEventNotification(NotificationEvent::ClaimEscalation, [
                'title' => (string) __('Reklamation überfällig: :number', ['number' => $case->number]),
                'message' => (string) __('Frist :due überschritten — bitte Fall :title weiterbearbeiten.', [
                    'due' => $case->due_at?->format('d.m.Y H:i'),
                    'title' => $case->title,
                ]),
                'url' => route('claims.show', $case),
            ]));
            $log->increment('recipient_count');
            $count++;
        }

        return $count;
    }
}
