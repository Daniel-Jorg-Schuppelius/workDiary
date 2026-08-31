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

namespace App\Services\ServiceTicket;

use App\Models\{Approval, User};
use Illuminate\Database\Eloquent\Model;

/**
 * EINE Genehmigungsmechanik (Feature 065, P7): gemeinsame Guards
 * (Selbstfreigabe-Sperre, Doppel-Entscheid, Pflichtgrund bei Ablehnung)
 * für ServiceRequest UND Change — die Domänen-Services reagieren nur noch
 * auf das Ergebnis (alle Schritte genehmigt / abgelehnt).
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
                'approver_rule' => ['type' => 'user', 'value' => (int) $delegate->id],
            ]);

            return 'pending';
        }

        if ($decision === 'rejected') {
            return 'rejected';
        }

        $open = Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->count();

        return $open === 0 ? 'approved_all' : 'pending';
    }

    /** Hat der Entscheider in derselben Kette schon eine Stufe entschieden? */
    private function hasDecidedEarlierStep(Approval $approval, User $actor): bool {
        return Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('id', '!=', $approval->id)
            ->where('decided_by', $actor->id)
            ->whereNotNull('decision')
            ->where('decision', '!=', 'question')
            ->exists();
    }

}
