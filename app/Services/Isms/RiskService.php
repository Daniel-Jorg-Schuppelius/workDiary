<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RiskService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{AssessmentKind, AssessmentStatus, RiskStatus};
use App\Models\Isms\{IsmsControl, IsmsRisk, IsmsRiskAssessment};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service ISMS-Risikoregister (Feature 044, MVP 1).
 *
 * Geschäftsregeln:
 * - risk_no: laufende Nummer je Organisation (Vergabe in der Transaktion,
 *   Unique-Index isms_risks_org_no_uq sichert Kollisionen ab).
 * - Neue Risiken werden dem Default-Scope der Organisation zugeordnet
 *   (Feature 046; ScopeService::ensureDefaultScope).
 * - score = likelihood * impact, wird hier berechnet und persistiert
 *   (Sortierung/Matrix laufen über die Spalte).
 * - Statusübergänge ausschließlich über transition() entlang
 *   RiskStatus::allowedTransitions().
 *
 * Bewertungshistorie (Feature 046, Inkrement D):
 * - createAssessment() legt IMMER einen neuen Entwurf an (kein
 *   Überschreiben); assessment_no läuft je Risiko (Vergabe in der
 *   Transaktion, Unique-Index isms_assessment_risk_no_uq).
 * - approveAssessment() setzt Person + Zeitpunkt (046-Prinzip „Freigabe
 *   mit Person/Zeitpunkt/Gegenstand"); freigegebene Bewertungen sind
 *   UNVERÄNDERLICH (Model-Guards in IsmsRiskAssessment).
 * - Sync: Das jüngste FREIGEGEBENE net-Assessment ist die maßgebliche
 *   aktuelle Bewertung — beim Approve eines net-Assessments werden
 *   risk.likelihood/impact/score nachgezogen, damit Matrix, Listen und
 *   Filter konsistent bleiben. gross/target berühren das Risiko nicht.
 * - Inline-Bearbeitung (create()/update() mit likelihood/impact) erzeugt
 *   automatisch ein selbst-freigegebenes net-Assessment („Direktbewertung")
 *   — lückenlose Historie ohne UI-Bruch.
 * - Restrisiko-Akzeptanz: der Übergang nach accepted erfordert ein
 *   freigegebenes net-Assessment mit valid_until (Ablauf-/Reviewdatum).
 *
 * Audit läuft über den Auditable-Trait (created/updated/deleted) plus
 * gezielte audit()-Events für Statusübergänge — eine eigene Event-Tabelle
 * gibt es bewusst nicht (Lebenszyklus trivial, analog Wissensbasis).
 */
class RiskService {
    public function __construct(
        private readonly ScopeService $scopes,
    ) {}

    /**
     * Legt ein Risiko an (Status default identified, Default-Scope).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsRisk {
        return DB::transaction(function () use ($creator, $attributes): IsmsRisk {
            $likelihood = (int) $attributes['likelihood'];
            $impact = (int) $attributes['impact'];

            $risk = IsmsRisk::query()->create([
                'organization_id' => $creator->organization_id,
                'isms_scope_id' => $this->scopes->ensureDefaultScope((int) $creator->organization_id)->id,
                'risk_no' => $this->nextRiskNo((int) $creator->organization_id),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'],
                'asset_ref' => $attributes['asset_ref'] ?? null,
                'threat' => $attributes['threat'] ?? null,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'score' => $likelihood * $impact,
                'treatment' => $attributes['treatment'],
                'status' => RiskStatus::Identified->value,
                'owner_user_id' => $attributes['owner_user_id'] ?? null,
                'review_due_on' => $attributes['review_due_on'] ?? null,
            ]);

            if (array_key_exists('control_ids', $attributes)) {
                $this->syncControls($risk, $this->normalizeControlIds($attributes['control_ids']));
            }

            // Direktbewertung: die Erst-Bewertung aus dem Dialog wird als
            // selbst-freigegebenes net-Assessment historisiert (046-D).
            $this->recordDirectAssessment($risk, $creator, $likelihood, $impact);

            return $risk;
        });
    }

    /**
     * Aktualisiert Stammdaten/Bewertung (Score wird neu berechnet);
     * der Status bleibt unangetastet — Übergänge laufen über transition().
     *
     * Ändert sich die Bewertung (likelihood/impact), entsteht automatisch
     * ein selbst-freigegebenes net-Assessment („Direktbewertung") — so
     * bleibt die Historie lückenlos, ohne den Inline-Workflow zu brechen
     * (Feature 046, Inkrement D).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsRisk $risk, User $actor, array $attributes): IsmsRisk {
        return DB::transaction(function () use ($risk, $actor, $attributes): IsmsRisk {
            $likelihood = (int) ($attributes['likelihood'] ?? $risk->likelihood);
            $impact = (int) ($attributes['impact'] ?? $risk->impact);
            $assessmentChanged = $likelihood !== (int) $risk->likelihood || $impact !== (int) $risk->impact;

            $risk->update([
                'title' => $attributes['title'] ?? $risk->title,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $risk->description,
                'category' => $attributes['category'] ?? $risk->category,
                'asset_ref' => array_key_exists('asset_ref', $attributes) ? $attributes['asset_ref'] : $risk->asset_ref,
                'threat' => array_key_exists('threat', $attributes) ? $attributes['threat'] : $risk->threat,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'score' => $likelihood * $impact,
                'treatment' => $attributes['treatment'] ?? $risk->treatment,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $risk->owner_user_id,
                'review_due_on' => array_key_exists('review_due_on', $attributes) ? $attributes['review_due_on'] : $risk->review_due_on,
            ]);

            if (array_key_exists('control_ids', $attributes)) {
                $this->syncControls($risk, $this->normalizeControlIds($attributes['control_ids']));
            }

            if ($assessmentChanged) {
                $this->recordDirectAssessment($risk, $actor, $likelihood, $impact);
            }

            return $risk;
        });
    }

    /**
     * Statusübergang entlang der State-Machine ({@see RiskStatus::allowedTransitions()}).
     *
     * Restrisiko-Akzeptanz (Feature 046, Inkrement D): der Übergang nach
     * accepted erfordert ein FREIGEGEBENES net-Assessment mit
     * valid_until (Ablauf-/Reviewdatum des akzeptierten Restrisikos).
     *
     * @throws ValidationException bei unzulässigem Übergang
     */
    public function transition(IsmsRisk $risk, RiskStatus $target, User $actor): IsmsRisk {
        if ($risk->status === $target) {
            return $risk;
        }

        if (! in_array($target, $risk->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $risk->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        if ($target === RiskStatus::Accepted) {
            $latestNet = $risk->latestApprovedNetAssessment();
            if ($latestNet === null || $latestNet->valid_until === null) {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.accept_requires_approved_net_assessment'),
                ]);
            }
        }

        return DB::transaction(function () use ($risk, $target, $actor): IsmsRisk {
            $from = $risk->status;
            $risk->update(['status' => $target->value]);
            $risk->audit('isms.risk.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $risk;
        });
    }

    /** Soft-Delete (Policy: isms.manage bzw. Admin). */
    public function delete(IsmsRisk $risk, User $actor): void {
        DB::transaction(function () use ($risk, $actor): void {
            // Fachliches Event VOR dem Delete, damit es gemeinsam mit dem
            // Auditable-`deleted` in der Hash-Kette landet.
            $risk->audit('isms.risk.deleted', ['actor_user_id' => $actor->id]);
            $risk->controls()->detach();
            $risk->delete();
        });
    }

    // ── Bewertungshistorie (Feature 046, Inkrement D) ──────────────────────

    /**
     * Erfasst einen neuen Bewertungsstand — IMMER als neuer Entwurf,
     * bestehende Stände werden nie überschrieben. assessment_no läuft
     * je Risiko (Vergabe in der Transaktion, Muster nextRiskNo()).
     *
     * @param  array<string, mixed>  $attributes  likelihood, impact, rationale?, valid_until?
     */
    public function createAssessment(IsmsRisk $risk, User $creator, AssessmentKind $kind, array $attributes): IsmsRiskAssessment {
        return DB::transaction(function () use ($risk, $creator, $kind, $attributes): IsmsRiskAssessment {
            $likelihood = (int) $attributes['likelihood'];
            $impact = (int) $attributes['impact'];

            return IsmsRiskAssessment::query()->create([
                'organization_id' => $risk->organization_id,
                'isms_risk_id' => $risk->id,
                'assessment_no' => $this->nextAssessmentNo((int) $risk->id),
                'kind' => $kind->value,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'score' => $likelihood * $impact,
                'rationale' => $attributes['rationale'] ?? null,
                'status' => AssessmentStatus::Draft->value,
                'valid_until' => $attributes['valid_until'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);
        });
    }

    /**
     * Freigabe (draft → approved): setzt Person + Zeitpunkt (046-Prinzip
     * „Freigabe mit Person/Zeitpunkt/Gegenstand"); danach ist der Stand
     * UNVERÄNDERLICH (Model-Guards in IsmsRiskAssessment).
     *
     * Sync-Semantik: Das jüngste FREIGEGEBENE net-Assessment ist die
     * maßgebliche aktuelle Bewertung des Risikos — beim Approve eines
     * net-Assessments werden risk.likelihood/impact/score nachgezogen,
     * damit Matrix, Listen und Filter konsistent bleiben. gross/target
     * dokumentieren nur und berühren das Risiko nicht.
     *
     * @throws ValidationException bei bereits freigegebener Bewertung
     */
    public function approveAssessment(IsmsRiskAssessment $assessment, User $actor): IsmsRiskAssessment {
        if ($assessment->isApproved()) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.assessment_already_approved'),
            ]);
        }

        return DB::transaction(function () use ($assessment, $actor): IsmsRiskAssessment {
            $assessment->update([
                'status' => AssessmentStatus::Approved->value,
                'approved_by_user_id' => $actor->id,
                'approved_at' => Carbon::now(),
            ]);

            $assessment->audit('isms.risk_assessment.approved', ['actor_user_id' => $actor->id]);

            if ($assessment->kind === AssessmentKind::Net) {
                $risk = $assessment->risk()->firstOrFail();
                $risk->update([
                    'likelihood' => $assessment->likelihood,
                    'impact' => $assessment->impact,
                    'score' => $assessment->likelihood * $assessment->impact,
                ]);
            }

            return $assessment;
        });
    }

    /**
     * Soft-Delete eines Bewertungs-ENTWURFS — freigegebene Stände sind
     * unlöschbarer Nachweis (der Model-Guard wirft zusätzlich).
     *
     * @throws ValidationException bei bereits freigegebener Bewertung
     */
    public function deleteAssessment(IsmsRiskAssessment $assessment, User $actor): void {
        if ($assessment->isApproved()) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.assessment_already_approved'),
            ]);
        }

        DB::transaction(function () use ($assessment, $actor): void {
            $assessment->audit('isms.risk_assessment.deleted', ['actor_user_id' => $actor->id]);
            $assessment->delete();
        });
    }

    /**
     * Direktbewertung (Bestands-Kompatibilität): Inline-Pflege von
     * likelihood/impact am Risiko erzeugt ein selbst-freigegebenes
     * net-Assessment — Freigabe durch die bearbeitende Person, Begründung
     * über den i18n-Key isms.assessment.rationale_direct.
     */
    private function recordDirectAssessment(IsmsRisk $risk, User $actor, int $likelihood, int $impact): void {
        $assessment = $this->createAssessment($risk, $actor, AssessmentKind::Net, [
            'likelihood' => $likelihood,
            'impact' => $impact,
            'rationale' => (string) __('isms.assessment.rationale_direct'),
        ]);

        $this->approveAssessment($assessment, $actor);
    }

    /** Nächste laufende Bewertungs-Nummer innerhalb eines Risikos. */
    private function nextAssessmentNo(int $riskId): int {
        $max = IsmsRiskAssessment::query()
            ->withTrashed()
            ->where('isms_risk_id', $riskId)
            ->lockForUpdate()
            ->max('assessment_no');

        return ((int) $max) + 1;
    }

    /**
     * Synchronisiert die Maßnahmen-Zuordnung. Die IDs werden über die
     * org-gescopte Control-Query aufgelöst — fremde Organisationen können
     * dadurch nicht verknüpft werden (Pivot trägt bewusst keine eigene
     * organization_id, siehe Migration).
     *
     * @param  list<int|string>  $controlIds
     */
    public function syncControls(IsmsRisk $risk, array $controlIds): void {
        $ids = IsmsControl::query()
            ->whereIn('id', array_map(intval(...), $controlIds))
            ->pluck('id')
            ->all();

        $risk->controls()->sync($ids);
    }

    /**
     * Normalisiert rohe Request-Werte zu einer ID-Liste (nur int/string).
     *
     * @return list<int|string>
     */
    private function normalizeControlIds(mixed $value): array {
        return array_values(array_filter(
            (array) $value,
            static fn(mixed $id): bool => is_int($id) || is_string($id),
        ));
    }

    /**
     * Daten für das 5x5-Matrix-Widget: [likelihood][impact] => Anzahl
     * offener Risiken (fehlende Zellen = 0).
     *
     * @return array<int, array<int, int>>
     */
    public function matrix(): array {
        $cells = [];
        foreach (range(1, 5) as $likelihood) {
            foreach (range(1, 5) as $impact) {
                $cells[$likelihood][$impact] = 0;
            }
        }

        foreach (IsmsRisk::query()->matrixCells()->get() as $row) {
            $cells[(int) $row->likelihood][(int) $row->impact] = (int) $row->risk_count;
        }

        return $cells;
    }

    /** Nächste laufende Risiko-Nummer der Organisation (innerhalb der Transaktion). */
    private function nextRiskNo(int $organizationId): int {
        $max = IsmsRisk::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->lockForUpdate()
            ->max('risk_no');

        return ((int) $max) + 1;
    }
}
