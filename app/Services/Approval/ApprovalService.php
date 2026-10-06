<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval;

use App\Models\Approval\Approval;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * EINE Genehmigungsmechanik (Feature 065, P7): gemeinsame Guards
 * (Selbstfreigabe-Sperre, Doppel-Entscheid, Pflichtgrund bei Ablehnung)
 * für ServiceRequest UND Change — die Domänen-Services reagieren nur noch
 * auf das Ergebnis (alle Schritte genehmigt / abgelehnt).
 *
 * Runden: `startNextRound()` löst die laufende Kette ab. Alle Regeln gelten
 * je Runde, entscheidbar ist nur die höchste Runde des Objekts; frühere
 * Runden bleiben als Historie stehen.
 */
class ApprovalService {
    /** @param array<int, array<string, mixed>> $chain */
    public function createChain(Model $approvable, array $chain): void {
        foreach (array_values($chain) as $index => $step) {
            Approval::query()->create([
                'organization_id' => (int) $approvable->getAttribute('organization_id'),
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'step' => $index + 1,
                'approver_rule' => (array) ($step['approver'] ?? $step),
            ]);
        }
    }

    /**
     * Neue Runde mit den Stufen der geltenden Runde, alle offen. Je Stufe
     * zählt die ursprüngliche Regel — eine Delegation galt nur ihrer Runde.
     *
     * @return int Nummer der neuen Runde
     */
    public function startNextRound(Model $approvable): int {
        $current = $this->chainOf($approvable)->currentRound()->orderBy('step')->orderBy('id')->get();
        if ($current->isEmpty()) {
            throw new \LogicException('Ohne Freigabekette gibt es keine neue Runde.');
        }

        $round = (int) $current->max('round') + 1;
        foreach ($current->unique('step') as $step) {
            Approval::query()->create([
                'organization_id' => (int) $step->organization_id,
                'approvable_type' => $step->approvable_type,
                'approvable_id' => $step->approvable_id,
                'step' => $step->step,
                'round' => $round,
                'approver_rule' => $step->approver_rule,
            ]);
        }

        return $round;
    }

    /** Geltende Runde des Objekts; 0 ohne Freigabekette. */
    public function currentRound(Model $approvable): int {
        return (int) $this->chainOf($approvable)->max('round');
    }

    /** Trägt die geltende Runde schon ein Urteil (Genehmigung oder Ablehnung)? Rückfrage und Delegation sind keines. */
    public function currentRoundHasVerdict(Model $approvable): bool {
        return $this->chainOf($approvable)->currentRound()->whereIn('decision', ['approved', 'rejected'])->exists();
    }

    /**
     * @return 'approved_all'|'rejected'|'pending' Gesamtzustand nach dem Entscheid
     */
    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $blockedUserId, ?int $delegateUserId = null): string {
        if (! in_array($decision, ['approved', 'rejected', 'question', 'delegated'], true)) {
            throw new \InvalidArgumentException('Unbekannte Entscheidung.');
        }
        if ($blockedUserId !== null && $blockedUserId === (int) $actor->id) {
            throw new \RuntimeException((string) __('Selbstfreigabe ist nicht zulässig.'));
        }
        if ($approval->decision !== null && $approval->decision !== 'question') {
            throw new \RuntimeException((string) __('Der Schritt ist bereits entschieden.'));
        }
        // Offene Stufen einer abgelösten Runde gelten einem überholten Stand.
        if ($this->isSuperseded($approval)) {
            throw new \RuntimeException((string) __('Diese Freigaberunde wurde durch eine neue abgelöst.'));
        }
        // Eine Ablehnung beendet die Kette — eine spätere Stufe darf sie nicht mehr aufheben.
        if ($this->chainRejected($approval)) {
            throw new \RuntimeException((string) __('Die Genehmigung wurde bereits abgelehnt.'));
        }
        // **Vier Augen heißt zwei Personen** (Sicherheitsscan 2026-08-23,
        // S-34). Geprüft wurde nur „Antragsteller ≠ Entscheider" — dieselbe
        // Person konnte also Stufe 1 und Stufe 2 derselben Kette entscheiden
        // und einen Investitionsantrag im Alleingang durchwinken, während die
        // Oberfläche „weitere Stufe offen (Vier-Augen)" meldete.
        if ($this->hasDecidedEarlierStep($approval, $actor)) {
            throw new \RuntimeException((string) __('Wer eine frühere Stufe entschieden hat, kann die nächste nicht ebenfalls entscheiden.'));
        }
        if ($decision === 'rejected' && trim((string) $reason) === '') {
            throw new \InvalidArgumentException((string) __('Ablehnung braucht eine Begründung.'));
        }
        if ($decision === 'delegated' && trim((string) $reason) === '') {
            throw new \InvalidArgumentException((string) __('Delegation braucht eine Begründung.'));
        }

        // Delegation (MVP-154): Empfänger ist Pflicht, org-gescopt und ≠ Antragsteller — die Selbstfreigabe-Sperre
        // greift beim Delegaten erneut (über blockedUserId auch bei dessen späterer Entscheidung).
        $delegate = null;
        if ($decision === 'delegated') {
            $delegate = $delegateUserId !== null
                ? User::query()
                    ->whereKey($delegateUserId)
                    ->where('organization_id', $approval->organization_id)
                    ->first()
                : null;
            if ($delegate === null) {
                throw new \InvalidArgumentException((string) __('Delegation braucht einen Empfänger der eigenen Organisation.'));
            }
            if ($blockedUserId !== null && (int) $delegate->id === $blockedUserId) {
                throw new \RuntimeException((string) __('Selbstfreigabe ist nicht zulässig.'));
            }
        }

        $approval->update([
            'decided_by' => $actor->id,
            'decision' => $decision,
            'reason' => $reason !== null ? trim($reason) : null,
            'decided_at' => now(),
        ]);

        if ($delegate !== null) {
            // Neuer offener Schritt mit gleicher step-Nummer: der Delegat übernimmt, die Kette wird nicht verlängert.
            Approval::query()->create([
                'organization_id' => (int) $approval->organization_id,
                'approvable_type' => $approval->approvable_type,
                'approvable_id' => $approval->approvable_id,
                'step' => $approval->step,
                'round' => $approval->round,
                'approver_rule' => ['type' => 'user', 'value' => (int) $delegate->id],
            ]);

            return 'pending';
        }

        if ($decision === 'rejected') {
            return 'rejected';
        }

        $open = $this->sameRound($approval)
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->count();

        return $open === 0 ? 'approved_all' : 'pending';
    }

    /** Wurde in der Runde dieses Schritts abgelehnt? */
    public function chainRejected(Approval $approval): bool {
        return $this->sameRound($approval)->where('decision', 'rejected')->exists();
    }

    /** Hat der Entscheider in derselben Runde schon eine Stufe entschieden? */
    private function hasDecidedEarlierStep(Approval $approval, User $actor): bool {
        return $this->sameRound($approval)
            ->where('id', '!=', $approval->id)
            ->where('decided_by', $actor->id)
            ->whereNotNull('decision')
            ->where('decision', '!=', 'question')
            ->exists();
    }

    private function isSuperseded(Approval $approval): bool {
        return Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('round', '>', $approval->round)
            ->exists();
    }

    /**
     * Schritte derselben Runde desselben Objekts.
     *
     * @return Builder<Approval>
     */
    private function sameRound(Approval $approval): Builder {
        return Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('round', $approval->round);
    }

    /**
     * Alle Schritte des Objekts über alle Runden.
     *
     * @return Builder<Approval>
     */
    private function chainOf(Model $approvable): Builder {
        return Approval::query()
            ->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey());
    }
}
