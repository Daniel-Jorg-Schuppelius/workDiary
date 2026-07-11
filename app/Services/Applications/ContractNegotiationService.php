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

use App\Models\Applications\{ApplicationContractNegotiation, ApplicationContractVersion, ApplicationOpportunity, JobApplication};
use App\Models\User;
use App\Services\ServiceTicket\ApprovalService;
use Illuminate\Support\Facades\DB;

/**
 * Vertragsverhandlung (Feature 068, MVP-195–197): versionierte Entwürfe/
 * Gegenentwürfe (append-only), Review-Punkte (offene Blocker verhindern
 * den Abschluss), zweistufige Freigabe (kaufmännisch + fachlich) über das
 * bestehende Approval-Modell inkl. Selbstfreigabe-Sperre.
 */
class ContractNegotiationService {
    public function __construct(private readonly ApprovalService $approvals) {}

    public function open(ApplicationOpportunity|JobApplication $parent, string $title, ?string $dueOn, User $actor): ApplicationContractNegotiation {
        if ($parent instanceof ApplicationOpportunity && $parent->status !== 'won') {
            throw new \RuntimeException((string) __('Vertragsverhandlungen starten erst nach der Gewinnentscheidung.'));
        }
        if ($parent instanceof JobApplication && ! in_array($parent->status, ['offer', 'accepted'], true)) {
            throw new \RuntimeException((string) __('Vertragsverhandlungen starten erst mit dem Angebot.'));
        }

        return DB::transaction(function () use ($parent, $title, $dueOn, $actor): ApplicationContractNegotiation {
            /** @var ApplicationContractNegotiation $negotiation */
            $negotiation = $parent->negotiations()->create([
                'organization_id' => $parent->getAttribute('organization_id'),
                'title' => $title,
                'status' => 'draft',
                'due_on' => $dueOn,
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);

            // Zweistufige Freigabe (MVP-195): kaufmännisch → fachlich/HR.
            $this->approvals->createChain($negotiation, [
                ['rule' => ['kind' => 'commercial']],
                ['rule' => ['kind' => $parent instanceof JobApplication ? 'hr' : 'technical']],
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
        if ($negotiation->isDecided()) {
            throw new \RuntimeException((string) __('Die Verhandlung ist abgeschlossen — keine neuen Versionen.'));
        }
        if (! in_array($kind, ApplicationContractVersion::KINDS, true)) {
            throw new \RuntimeException((string) __('Ungültige Versionsart.'));
        }

        return DB::transaction(function () use ($negotiation, $kind, $summary, $conditions, $actor, $documentId): ApplicationContractVersion {
            $payload = $conditions !== [] ? (string) json_encode($conditions) : null;
            $version = ApplicationContractVersion::query()->create([
                'organization_id' => $negotiation->organization_id,
                'negotiation_id' => $negotiation->id,
                'version' => (int) $negotiation->versions()->max('version') + 1,
                'kind' => $kind,
                'summary' => $summary,
                'conditions' => $payload,
                'document_id' => $documentId,
                'sha256' => $payload !== null ? hash('sha256', $payload) : null,
                'created_by' => $actor->id,
            ]);

            $negotiation->update(['status' => $kind === 'counter' ? 'counter' : 'in_review']);
            $negotiation->audit('contract.version_added', ['version' => $version->version, 'kind' => $kind]);

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
            'status' => 'open',
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
            'status' => $resolution,
            'note' => $note ?? $item->note,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ]);
        $negotiation->audit('contract.review_item_resolved', ['label' => $item->label, 'resolution' => $resolution]);
    }

    /** Freigabe der nächsten offenen Stufe (Selbstfreigabe-Sperre: Ersteller). */
    public function approve(ApplicationContractNegotiation $negotiation, User $actor, ?string $reason = null): string {
        if ($negotiation->isDecided()) {
            throw new \RuntimeException((string) __('Die Verhandlung ist abgeschlossen.'));
        }
        $pending = $negotiation->approvals()
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->orderBy('step')
            ->first();
        if ($pending === null) {
            throw new \RuntimeException((string) __('Keine offene Freigabestufe.'));
        }

        $result = $this->approvals->decide($pending, $actor, 'approved', $reason, (int) $negotiation->created_by);
        if ($result === 'approved_all') {
            $negotiation->update(['status' => 'approved']);
        }
        $negotiation->audit('contract.approved_step', ['step' => $pending->step, 'result' => $result]);

        return $result;
    }

    /**
     * Abschluss (MVP-196): nur ohne offene Blocker und nach vollständiger
     * Freigabe — Abweichungen werden VOR der Übergabe sichtbar entschieden.
     */
    public function conclude(ApplicationContractNegotiation $negotiation, string $decision, ?string $note, User $actor): ApplicationContractNegotiation {
        if (! in_array($decision, ['concluded', 'declined'], true)) {
            throw new \RuntimeException((string) __('Ungültige Abschluss-Entscheidung.'));
        }
        if ($negotiation->isDecided()) {
            throw new \RuntimeException((string) __('Die Verhandlung ist bereits abgeschlossen.'));
        }
        if ($decision === 'concluded') {
            if ($negotiation->hasOpenBlockers()) {
                throw new \RuntimeException((string) __('Offene Blocker-Punkte müssen vor dem Abschluss entschieden werden.'));
            }
            if ($negotiation->status !== 'approved') {
                throw new \RuntimeException((string) __('Der Abschluss braucht die vollständige Freigabe (kaufmännisch + fachlich).'));
            }
            if ((int) $negotiation->versions()->count() === 0) {
                throw new \RuntimeException((string) __('Ohne Vertragsversion gibt es nichts abzuschließen.'));
            }
        }

        $negotiation->update([
            'status' => $decision,
            'decision' => $decision,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
        $negotiation->audit('contract.concluded', ['decision' => $decision]);

        return $negotiation->refresh();
    }
}
