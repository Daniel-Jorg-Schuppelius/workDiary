<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningUnitLinkService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningProgressStatus, LearningUnitKind};
use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\Learning\{LearningEnrollment, LearningUnit, LearningUnitProgress};
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureTemplate};
use App\Models\Survey\{Survey, SurveyInvitation};
use App\Services\Survey\SurveyService;
use App\Support\OrganizationContext;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Einheiten, die auf ein anderes Modul oder eine fremde Adresse zeigen
 * (Feature 149, MVP-1600): Prozedur, Umfrage, Extern. Der Verweis steht in
 * `completion_rule`; abgeschlossen wird über das Ergebnis dort, nie von Hand.
 */
class LearningUnitLinkService {
    /** Schlüssel in `completion_rule`, die diese Klasse verwaltet. */
    public const array RULE_KEYS = ['procedure_template_id', 'survey_id', 'external_url', 'proof_required'];

    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
        private readonly SurveyService $surveys,
    ) {}

    /**
     * Verweis der Einheit prüfen und als Regelteil liefern. Ohne Verweis
     * wäre die Art funktionslos — deshalb ist er Pflicht.
     *
     * @param  array{procedure_template_id?: int|null, survey_id?: int|null, external_url?: string|null, proof_required?: bool}  $input
     * @return array<string, int|string|bool>
     */
    public function ruleFor(LearningUnitKind $kind, array $input, int $organizationId): array {
        switch ($kind) {
            case LearningUnitKind::Procedure:
                $id = (int) ($input['procedure_template_id'] ?? 0);
                $exists = $id > 0 && ProcedureTemplate::query()->withoutGlobalScopes()
                    ->whereKey($id)->where('organization_id', $organizationId)->where('active', true)->exists();
                if (! $exists) {
                    throw ValidationException::withMessages(['procedure_template' => (string) __('learning.errors.procedure_invalid')]);
                }

                return ['procedure_template_id' => $id];

            case LearningUnitKind::Survey:
                $id = (int) ($input['survey_id'] ?? 0);
                $exists = $id > 0 && Survey::query()->withoutGlobalScopes()
                    ->whereKey($id)->where('organization_id', $organizationId)->where('active', true)->exists();
                if (! $exists) {
                    throw ValidationException::withMessages(['survey' => (string) __('learning.errors.survey_invalid')]);
                }

                return ['survey_id' => $id];

            case LearningUnitKind::External:
                $url = trim((string) ($input['external_url'] ?? ''));
                if ($url === '' || ! in_array(mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                    throw ValidationException::withMessages(['external_url' => (string) __('learning.errors.external_url_required')]);
                }

                return ['external_url' => $url, ...(($input['proof_required'] ?? false) ? ['proof_required' => true] : [])];

            default:
                return [];
        }
    }

    /**
     * Fragebogen der Einheit für diese Einschreibung öffnen: Einladung
     * anlegen, am Fortschritt merken, Adresse der Teilnahme liefern.
     */
    public function openSurvey(LearningEnrollment $enrollment, LearningUnit $unit): string {
        $survey = $unit->kind === LearningUnitKind::Survey && $unit->surveyId() !== null
            ? Survey::query()->withoutGlobalScopes()->whereKey($unit->surveyId())->where('organization_id', $enrollment->organization_id)->first()
            : null;
        $email = trim((string) ($enrollment->user->email ?? $enrollment->externalParticipant->email ?? ''));

        if ($survey === null || $email === '') {
            throw ValidationException::withMessages(['unit' => (string) __('learning.errors.survey_unavailable')]);
        }
        if ($enrollment->status->isFinal() || $enrollment->isAccessExpired()) {
            throw ValidationException::withMessages(['unit' => (string) __('learning.errors.enrollment_closed')]);
        }
        $this->enrollments->guardReleased($enrollment, $unit);

        try {
            $issued = $this->surveys->invite($survey, $email, null, 'learning', selfRequested: true);
        } catch (RuntimeException $e) {
            // Inaktiv oder ohne Fragen — die Meldung des Umfragedienstes nennt den Grund.
            throw ValidationException::withMessages(['unit' => $e->getMessage()]);
        }

        $progress = LearningUnitProgress::query()->firstOrNew([
            'learning_enrollment_id' => $enrollment->id,
            'learning_unit_id' => $unit->id,
        ]);
        $progress->fill([
            'organization_id' => $enrollment->organization_id,
            'status' => $progress->exists ? $progress->status : LearningProgressStatus::Started,
            'started_at' => $progress->started_at ?? now(),
            'survey_invitation_id' => $issued['invitation']->id,
        ])->save();

        return route('surveys.public-show', $issued['token']);
    }

    /** Ein abgesendeter Fragebogen schließt die Einheit ab, zu der die Einladung gehört. */
    public function completeBySurveyInvitation(SurveyInvitation $invitation): void {
        $progress = LearningUnitProgress::query()
            ->withoutGlobalScopes()
            ->with(['enrollment', 'unit'])
            ->where('survey_invitation_id', $invitation->id)
            ->first();
        $enrollment = $progress?->enrollment;
        $unit = $progress?->unit;

        $organization = $enrollment?->organization;

        if ($enrollment === null || $unit === null || $organization === null || $progress->status === LearningProgressStatus::Completed) {
            return;
        }

        // Die Teilnahme läuft ohne Anmeldung: der Mandant kommt aus der Einschreibung.
        OrganizationContext::run($organization, fn () => $this->completeQuietly($enrollment, $unit));
    }

    /**
     * Ein abgeschlossener Prozedurlauf schließt die Prozedur-Einheiten der
     * ausführenden und der zugewiesenen Person ab, die auf seine Vorlage zeigen.
     */
    public function completeByProcedureRun(ProcedureRun $run, ?User $actor): void {
        if ($run->status !== ProcedureRunStatus::Completed) {
            return;
        }
        $templateId = (int) ($run->templateVersion->procedure_template_id ?? 0);
        $userIds = array_values(array_unique(array_filter([$actor?->id, $run->assigned_user_id])));
        if ($templateId <= 0 || $userIds === []) {
            return;
        }

        $units = LearningUnit::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $run->organization_id)
            ->where('kind', LearningUnitKind::Procedure->value)
            ->get()
            ->filter(static fn (LearningUnit $unit): bool => $unit->procedureTemplateId() === $templateId);

        foreach ($units as $unit) {
            $open = LearningEnrollment::query()
                ->withoutGlobalScopes()
                ->where('learning_course_id', $unit->learning_course_id)
                ->whereIn('user_id', $userIds)
                ->get()
                ->reject(static fn (LearningEnrollment $enrollment): bool => $enrollment->status->isFinal());

            foreach ($open as $enrollment) {
                $done = LearningUnitProgress::query()->withoutGlobalScopes()
                    ->where('learning_enrollment_id', $enrollment->id)
                    ->where('learning_unit_id', $unit->id)
                    ->where('status', LearningProgressStatus::Completed->value)
                    ->exists();
                if (! $done) {
                    $this->completeQuietly($enrollment, $unit);
                }
            }
        }
    }

    /**
     * Gesperrte oder abgelaufene Einschreibungen bleiben offen — der Lauf
     * oder die Antwort selbst darf daran nicht scheitern.
     */
    private function completeQuietly(LearningEnrollment $enrollment, LearningUnit $unit): void {
        try {
            $this->enrollments->completeUnit($enrollment, $unit);
        } catch (ValidationException) {
            // Freischaltplan, Mindestverweildauer oder Zugang: die Einheit bleibt offen und sichtbar.
        }
    }
}
