<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Models\Applications\{ApplicationOpportunity, ApplicationSubmission};
use App\Models\{Project, User};
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Facades\DB;

/**
 * Ausschreibungs-Lifecycle (Feature 068, MVP-184/187): Go-/No-go,
 * versionierte Einreichungspakete (Snapshot + SHA-256), Entscheidung
 * (gewonnen/verloren/zurückgezogen) und kontrollierte Überführung in ein
 * Projekt — verlorene Akten bleiben mit Grund auswertbar.
 */
class TenderService {
    public function decideGo(ApplicationOpportunity $opportunity, string $decision, ?string $note, User $actor): ApplicationOpportunity {
        if (! in_array($decision, ['go', 'no_go'], true)) {
            throw new \RuntimeException((string) __('Ungültige Go-/No-go-Entscheidung.'));
        }
        if (! $opportunity->isOpen()) {
            throw new \RuntimeException((string) __('Die Akte ist bereits entschieden.'));
        }

        $opportunity->update([
            'go_decision' => $decision,
            'go_decided_by' => $actor->id,
            'go_decided_at' => now(),
            'go_note' => $note,
            // No-go beendet die Akte nachvollziehbar (zurückgezogen).
            'status' => $decision === 'no_go' ? 'withdrawn' : $opportunity->status,
            'loss_reason' => $decision === 'no_go' ? ($note ?? (string) __('Interne No-go-Entscheidung.')) : $opportunity->loss_reason,
        ]);
        $opportunity->audit('tender.go_decided', ['decision' => $decision]);

        return $opportunity->refresh();
    }

    /**
     * Einreichungspaket (MVP-187): friert Titel, Wert, Anforderungen und
     * verknüpfte Dokumente als Snapshot ein — wiederholte Einreichungen
     * erzeugen neue Versionen (Historie), nie Überschreibung.
     */
    public function submit(ApplicationOpportunity $opportunity, string $channel, ?string $note, User $actor): ApplicationSubmission {
        if ($opportunity->go_decision !== 'go') {
            throw new \RuntimeException((string) __('Vor der Einreichung braucht die Akte eine Go-Entscheidung.'));
        }
        if (! $opportunity->isOpen()) {
            throw new \RuntimeException((string) __('Die Akte ist bereits entschieden.'));
        }

        $openRequired = $opportunity->requirements()
            ->where('required', true)
            ->whereNotIn('status', ['done', 'not_applicable'])
            ->count();
        if ($openRequired > 0) {
            throw new \RuntimeException((string) __(':count Pflicht-Unterlagen sind noch offen.', ['count' => $openRequired]));
        }

        return DB::transaction(function () use ($opportunity, $channel, $note, $actor): ApplicationSubmission {
            $snapshot = [
                'title' => $opportunity->title,
                'kind' => $opportunity->kind,
                'estimated_value' => $opportunity->estimated_value,
                'submission_deadline' => optional($opportunity->submission_deadline)->toDateString(),
                'requirements' => $opportunity->requirements->map(fn(\App\Models\Applications\ApplicationRequirement $requirement): array => [
                    'label' => $requirement->label,
                    'kind' => $requirement->kind,
                    'required' => (bool) $requirement->required,
                    'status' => $requirement->status,
                    'document_id' => $requirement->document_id,
                ])->all(),
                'submitted_at' => now()->toIso8601String(),
            ];

            $version = (int) $opportunity->submissions()->max('version') + 1;
            $submission = ApplicationSubmission::query()->create([
                'organization_id' => $opportunity->organization_id,
                'application_opportunity_id' => $opportunity->id,
                'version' => $version,
                'channel' => $channel,
                'snapshot' => $snapshot,
                'sha256' => CryptoHelper::hash(JsonHelper::encode($snapshot)),
                'note' => $note,
                'submitted_by' => $actor->id,
            ]);

            $opportunity->update(['status' => 'submitted']);
            $opportunity->audit('tender.submitted', ['version' => $version, 'sha256' => $submission->sha256, 'channel' => $channel]);

            return $submission;
        });
    }

    /** Zuschlags-Entscheidung: gewonnen/verloren/zurückgezogen mit Grund. */
    public function decide(ApplicationOpportunity $opportunity, string $decision, ?string $reason, User $actor): ApplicationOpportunity {
        if (! in_array($decision, ['won', 'lost', 'withdrawn'], true)) {
            throw new \RuntimeException((string) __('Ungültige Entscheidung.'));
        }
        if (! $opportunity->isOpen()) {
            throw new \RuntimeException((string) __('Die Akte ist bereits entschieden.'));
        }
        if (in_array($decision, ['lost', 'withdrawn'], true) && ($reason === null || trim($reason) === '')) {
            throw new \RuntimeException((string) __('Verlust/Rückzug braucht einen Grund (Auswertung).'));
        }

        $opportunity->update([
            'status' => $decision,
            'loss_reason' => $decision === 'won' ? null : $reason,
        ]);
        $opportunity->audit('tender.decided', ['decision' => $decision, 'by' => $actor->id]);

        return $opportunity->refresh();
    }

    /**
     * Kontrollierte Überführung nach Gewinn (MVP-187): verknüpft ein
     * bestehendes Projekt oder legt ein neues an — die Akte bleibt als
     * Nachweis erhalten (kein stiller Datenumzug).
     */
    public function transferToProject(ApplicationOpportunity $opportunity, ?Project $existing, User $actor): Project {
        if ($opportunity->status !== 'won') {
            throw new \RuntimeException((string) __('Nur gewonnene Ausschreibungen werden überführt.'));
        }

        return DB::transaction(function () use ($opportunity, $existing, $actor): Project {
            $project = $existing;
            if ($project === null) {
                if ($opportunity->customer_id === null) {
                    throw new \RuntimeException((string) __('Für ein neues Projekt braucht die Akte einen Kunden.'));
                }
                $project = Project::query()->create([
                    'organization_id' => $opportunity->organization_id,
                    'customer_id' => $opportunity->customer_id,
                    'name' => $opportunity->title,
                    'description' => (string) __('Aus Ausschreibung „:title" überführt.', ['title' => $opportunity->title]),
                ]);
            }

            $opportunity->update(['project_id' => $project->id]);
            $opportunity->audit('tender.transferred', ['project_id' => $project->id, 'by' => $actor->id]);

            return $project;
        });
    }
}
