<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Models\{Approval, Change, ChangeTemplate, User};
use Illuminate\Support\Facades\DB;

/**
 * Change Management (Feature 065, MVP-157). Typ-Regeln: standard nur aus
 * FREIGEGEBENER Vorlage (Stand wird als template_snapshot eingefroren,
 * keine Genehmigungskette nötig); normal/emergency verlangen einen
 * Rollback-Plan; emergency fährt eine VERKÜRZTE Kette (max. 1 Schritt)
 * und erzwingt das PIR vor dem Abschluss.
 */
class ChangeService {
    /**
     * @param array<string, mixed> $attributes
     * @param array<int, array<string, mixed>> $approvalChain
     */
    public function submit(array $attributes, User $actor, array $approvalChain = [], ?ChangeTemplate $template = null): Change {
        $type = (string) ($attributes['change_type'] ?? 'normal');

        if ($type === 'standard') {
            if ($template === null || ! $template->approved) {
                throw new \RuntimeException((string) __('Standard-Changes brauchen eine freigegebene Vorlage.'));
            }
        } elseif (trim((string) ($attributes['rollback_plan'] ?? ($template->rollback_plan ?? ''))) === '') {
            throw new \InvalidArgumentException((string) __('Normal- und Emergency-Changes brauchen einen Rollback-Plan.'));
        }

        if ($type === 'emergency' && count($approvalChain) > 1) {
            // Verkürzte Kette: genau eine Freigabe-Instanz.
            $approvalChain = [reset($approvalChain)];
        }

        return DB::transaction(function () use ($attributes, $actor, $approvalChain, $template, $type): Change {
            $change = Change::query()->create([
                ...$attributes,
                'organization_id' => (int) $actor->organization_id,
                'change_type' => $type,
                'change_template_id' => $template?->id,
                'template_snapshot' => $template !== null ? [
                    'template_id' => $template->id,
                    'name' => $template->name,
                    'version' => $template->version,
                    'implementation_plan' => $template->implementation_plan,
                    'test_plan' => $template->test_plan,
                    'rollback_plan' => $template->rollback_plan,
                ] : null,
                'implementation_plan' => $attributes['implementation_plan'] ?? $template?->implementation_plan,
                'test_plan' => $attributes['test_plan'] ?? $template?->test_plan,
                'rollback_plan' => $attributes['rollback_plan'] ?? $template?->rollback_plan,
                'status' => ($type === 'standard' || $approvalChain === []) ? 'approved' : 'pending_approval',
                'created_by' => $actor->id,
            ]);

            if ($type !== 'standard' && $approvalChain !== []) {
                app(ApprovalService::class)->createChain($change, $approvalChain);
            }

            $change->audit('change.submitted', ['type' => $type, 'template_version' => $template?->version]);

            return $change->refresh();
        });
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason = null): Change {
        /** @var Change $change */
        $change = $approval->approvable()->firstOrFail();

        return DB::transaction(function () use ($approval, $actor, $decision, $reason, $change): Change {
            $outcome = app(ApprovalService::class)->decide($approval, $actor, $decision, $reason, (int) $change->created_by);

            if ($outcome === 'rejected') {
                $change->update(['status' => 'cancelled', 'outcome' => 'cancelled']);
            } elseif ($outcome === 'approved_all') {
                $change->update(['status' => 'approved']);
            }

            $change->audit('change.decided', ['step' => $approval->step, 'decision' => $decision]);

            return $change->refresh();
        });
    }

    /** Umsetzung starten — optional als ProcedureRun (subject=Change). */
    public function implement(Change $change, User $actor, ?int $procedureTemplateId = null): Change {
        if ($change->status !== 'approved') {
            throw new \RuntimeException((string) __('Nur genehmigte Changes können umgesetzt werden.'));
        }

        $change->update(['status' => 'implementing']);

        if ($procedureTemplateId !== null) {
            $template = \App\Models\ProcedureTemplate::query()
                ->where('organization_id', $change->organization_id)
                ->findOrFail($procedureTemplateId);
            app(\App\Services\Procedure\ProcedureExecutionService::class)->start($template, $change, $actor);
        }

        $change->audit('change.implementing', ['actor' => $actor->id]);

        return $change->refresh();
    }

    /**
     * Abschluss mit Outcome; Emergency-Changes erzwingen das PIR
     * (pir_notes) VOR dem Abschluss — sonst Exception.
     */
    public function complete(Change $change, User $actor, string $outcome, ?string $pirNotes = null): Change {
        if (! in_array($outcome, Change::OUTCOMES, true)) {
            throw new \InvalidArgumentException("Unbekanntes Outcome: {$outcome}");
        }
        if (! in_array($change->status, ['approved', 'implementing'], true)) {
            throw new \RuntimeException((string) __('Nur laufende Changes können abgeschlossen werden.'));
        }
        if ($change->change_type === 'emergency' && trim((string) ($pirNotes ?? $change->pir_notes)) === '') {
            throw new \InvalidArgumentException((string) __('Emergency-Changes brauchen ein PIR vor dem Abschluss.'));
        }

        $change->update([
            'status' => 'done',
            'outcome' => $outcome,
            'pir_notes' => $pirNotes ?? $change->pir_notes,
            'pir_done_at' => ($pirNotes ?? $change->pir_notes) !== null ? now() : null,
        ]);

        $change->audit('change.completed', ['outcome' => $outcome, 'actor' => $actor->id]);

        return $change->refresh();
    }
}
