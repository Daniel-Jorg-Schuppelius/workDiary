<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractNegotiationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\Applications\{ApplicationContractNegotiationStatus, ApplicationContractReviewStatus, ApplicationOpportunityStatus, JobApplicationStatus};
use App\Enums\Approval\ApprovalStepKind;
use App\Models\Applications\{ApplicationContractNegotiation, ApplicationContractVersion, ApplicationOpportunity, JobApplication};
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Services\Approval\ApprovalService;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Facades\DB;

/**
 * Vertragsverhandlung (Feature 068, MVP-195–197): versionierte Entwürfe/
 * Gegenentwürfe (append-only), Review-Punkte (offene Blocker verhindern
 * den Abschluss), zweistufige Freigabe (kaufmännisch + fachlich) über das
 * bestehende Approval-Modell inkl. Selbstfreigabe-Sperre.
 *
 * Eine Freigabe gilt einer Version: trägt die geltende Freigaberunde schon
 * ein Urteil, startet eine neue Version die nächste Runde.
 */
class ContractNegotiationService {
    public function __construct(private readonly ApprovalService $approvals) {}

    public function open(ApplicationOpportunity|JobApplication $parent, string $title, ?string $dueOn, User $actor): ApplicationContractNegotiation {
        if ($parent instanceof ApplicationOpportunity && $parent->status !== ApplicationOpportunityStatus::Won) {
            throw new \RuntimeException((string) __('Vertragsverhandlungen starten erst nach der Gewinnentscheidung.'));
        }
        if ($parent instanceof JobApplication && ! in_array($parent->status, [JobApplicationStatus::Offer, JobApplicationStatus::Accepted], true)) {
            throw new \RuntimeException((string) __('Vertragsverhandlungen starten erst mit dem Angebot.'));
        }

        return DB::transaction(function () use ($parent, $title, $dueOn, $actor): ApplicationContractNegotiation {
            /** @var ApplicationContractNegotiation $negotiation */
            $negotiation = $parent->negotiations()->create([
                'organization_id' => $parent->getAttribute('organization_id'),
                'title' => $title,
                'status' => ApplicationContractNegotiationStatus::Draft,
                'due_on' => $dueOn,
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);

            // Zweistufige Freigabe (MVP-195): kaufmännisch → fachlich/HR.
            $this->approvals->createChain($negotiation, [
                ['rule' => ['kind' => ApprovalStepKind::Commercial->value]],
                ['rule' => ['kind' => ($parent instanceof JobApplication ? ApprovalStepKind::Hr : ApprovalStepKind::Technical)->value]],
            ]);

            $negotiation->audit('contract.negotiation_opened', ['parent' => $parent->getMorphClass()]);

            return $negotiation;
        });
    }

    /**
     * Neue Vertragsversion (append-only): Entwurf/Gegenentwurf/Endstand mit
     * optionalen strukturierten Konditionen (verschlüsselt) und DMS-Dokument.
     *
     * @param array<string, mixed> $conditions
     */
    public function addVersion(ApplicationContractNegotiation $negotiation, string $kind, ?string $summary, array $conditions, User $actor, ?int $documentId = null): ApplicationContractVersion {
        if (! in_array($kind, ApplicationContractVersion::KINDS, true)) {
            throw new \RuntimeException((string) __('Ungültige Versionsart.'));
        }

        return $this->serialized($negotiation, function () use ($negotiation, $kind, $summary, $conditions, $actor, $documentId): ApplicationContractVersion {
            if ($negotiation->isDecided()) {
                throw new \RuntimeException((string) __('Die Verhandlung ist abgeschlossen — keine neuen Versionen.'));
            }

            // Erteilte Stufen gelten dem bisherigen Stand; ohne Urteil bleibt die laufende Runde.
            $restarted = $this->approvals->currentRoundHasVerdict($negotiation);
            $round = $restarted
                ? $this->approvals->startNextRound($negotiation)
                : max(1, $this->approvals->currentRound($negotiation));

            $payload = $conditions !== [] ? JsonHelper::encode($conditions) : null;
            $version = ApplicationContractVersion::query()->create([
                'organization_id' => $negotiation->organization_id,
                'negotiation_id' => $negotiation->id,
                'version' => (int) $negotiation->versions()->max('version') + 1,
                'approval_round' => $round,
                'kind' => $kind,
                'summary' => $summary,
                'conditions' => $payload,
                'document_id' => $documentId,
                'sha256' => CryptoHelper::hash($payload), // null-sicher: null → null
                'created_by' => $actor->id,
            ]);

            $negotiation->update(['status' => $kind === 'counter' ? ApplicationContractNegotiationStatus::Counter : ApplicationContractNegotiationStatus::InReview]);
            $negotiation->audit('contract.version_added', ['version' => $version->version, 'kind' => $kind]);
            if ($restarted) {
                $negotiation->audit('contract.approval_restarted', ['round' => $round, 'version' => $version->version]);
            }

            return $version;
        });
    }

    public function addReviewItem(ApplicationContractNegotiation $negotiation, string $label, string $severity, ?string $note, User $actor): void {
        if ($negotiation->isDecided()) {
            throw new \RuntimeException((string) __('Die Verhandlung ist abgeschlossen.'));
        }
        $negotiation->reviewItems()->create([
            'organization_id' => $negotiation->organization_id,
            'label' => $label,
            'severity' => $severity,
            'status' => ApplicationContractReviewStatus::Open,
            'note' => $note,
        ]);
        $negotiation->audit('contract.review_item_added', ['label' => $label, 'severity' => $severity, 'by' => $actor->id]);
    }

    public function resolveReviewItem(ApplicationContractNegotiation $negotiation, int $itemId, string $resolution, ?string $note, User $actor): void {
        if (! in_array($resolution, ['resolved', 'accepted'], true)) {
            throw new \RuntimeException((string) __('Ungültige Auflösung.'));
        }
        $item = $negotiation->reviewItems()->whereKey($itemId)->firstOrFail();
        $item->update([
            'status' => ApplicationContractReviewStatus::from($resolution),
            'note' => $note ?? $item->note,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ]);
        $negotiation->audit('contract.review_item_resolved', ['label' => $item->label, 'resolution' => $resolution]);
    }

    /** Freigabe der nächsten offenen Stufe der geltenden Runde (Selbstfreigabe-Sperre: Ersteller). */
    public function approve(ApplicationContractNegotiation $negotiation, User $actor, ?string $reason = null): string {
        return $this->serialized($negotiation, function () use ($negotiation, $actor, $reason): string {
            if ($negotiation->isDecided()) {
                throw new \RuntimeException((string) __('Die Verhandlung ist abgeschlossen.'));
            }
            $pending = $negotiation->approvals()
                ->currentRound()
                ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
                ->orderBy('step')
                ->first();
            if ($pending === null) {
                throw new \RuntimeException((string) __('Keine offene Freigabestufe.'));
            }

            return $this->applyDecision($negotiation, $pending, $actor, 'approved', $reason, null);
        });
    }

    /**
     * Entscheidung einer bestimmten Stufe aus dem Genehmigungs-Eingang — mit
     * denselben Folgen wie die Freigabe an der Akte.
     *
     * @return 'approved_all'|'rejected'|'pending'
     */
    public function decide(Approval $approval, User $actor, string $decision, ?string $reason = null, ?int $delegateUserId = null): string {
        $negotiation = $approval->approvable;
        if (! $negotiation instanceof ApplicationContractNegotiation) {
            throw new \LogicException('Die Stufe gehört zu keiner Vertragsverhandlung.');
        }

        return $this->serialized($negotiation, function () use ($negotiation, $approval, $actor, $decision, $reason, $delegateUserId): string {
            if ($negotiation->isDecided()) {
                throw new \RuntimeException((string) __('Die Verhandlung ist abgeschlossen.'));
            }

            return $this->applyDecision($negotiation, $approval->refresh(), $actor, $decision, $reason, $delegateUserId);
        });
    }

    /**
     * Abschluss (MVP-196): nur ohne offene Blocker und nach vollständiger
     * Freigabe — Abweichungen werden VOR der Übergabe sichtbar entschieden.
     */
    public function conclude(ApplicationContractNegotiation $negotiation, string $decision, ?string $note, User $actor): ApplicationContractNegotiation {
        if (! in_array($decision, ['concluded', 'declined'], true)) {
            throw new \RuntimeException((string) __('Ungültige Abschluss-Entscheidung.'));
        }

        return $this->serialized($negotiation, function () use ($negotiation, $decision, $note, $actor): ApplicationContractNegotiation {
            if ($negotiation->isDecided()) {
                throw new \RuntimeException((string) __('Die Verhandlung ist bereits abgeschlossen.'));
            }
            if ($decision === 'concluded') {
                if ($negotiation->hasOpenBlockers()) {
                    throw new \RuntimeException((string) __('Offene Blocker-Punkte müssen vor dem Abschluss entschieden werden.'));
                }
                if (! $negotiation->status->canTransitionTo(ApplicationContractNegotiationStatus::Concluded)) {
                    throw new \RuntimeException((string) __('Der Abschluss braucht die vollständige Freigabe (kaufmännisch + fachlich).'));
                }
                if ((int) $negotiation->versions()->count() === 0) {
                    throw new \RuntimeException((string) __('Ohne Vertragsversion gibt es nichts abzuschließen.'));
                }
            }

            $negotiation->update([
                'status' => ApplicationContractNegotiationStatus::from($decision),
                'decision' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
            $negotiation->audit('contract.concluded', ['decision' => $decision]);

            return $negotiation->refresh();
        });
    }

    /**
     * Genehmigung, Ablehnung, Rückfrage oder Delegation (Guards im
     * ApprovalService). Eine Ablehnung beendet die Runde, der Status bleibt —
     * eine neue Version startet die nächste Runde.
     *
     * @return 'approved_all'|'rejected'|'pending'
     */
    private function applyDecision(ApplicationContractNegotiation $negotiation, Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): string {
        $result = $this->approvals->decide($approval, $actor, $decision, $reason, (int) $negotiation->created_by, $delegateUserId);
        if ($result === 'approved_all') {
            $negotiation->update(['status' => ApplicationContractNegotiationStatus::Approved]);
        }
        $negotiation->audit(match ($decision) {
            'approved' => 'contract.approved_step',
            'rejected' => 'contract.rejected_step',
            'question' => 'contract.question_step',
            default => 'contract.delegated_step',
        }, ['step' => $approval->step, 'round' => $approval->round, 'result' => $result]);

        return $result;
    }

    /**
     * Neue Version, Freigabe und Abschluss lesen und schreiben denselben Stand
     * (Status, geltende Runde) und laufen je Verhandlung nacheinander — sonst
     * landet eine Freigabe in einer Runde, die gerade abgelöst wird.
     *
     * @template T
     *
     * @param \Closure(): T $work
     * @return T
     */
    private function serialized(ApplicationContractNegotiation $negotiation, \Closure $work): mixed {
        return DB::transaction(function () use ($negotiation, $work): mixed {
            $negotiation->newQueryWithoutScopes()->whereKey($negotiation->getKey())->lockForUpdate()->value('id');
            $negotiation->refresh();

            return $work();
        });
    }
}
