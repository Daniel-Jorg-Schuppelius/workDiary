<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsProcedureRuns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure\Concerns;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureStepRun, ProcedureTemplate, ProcedureTemplateVersion};
use App\Services\Procedure\{ProcedureExecutionService, ProcedureTemplateService};
use Illuminate\Database\Eloquent\Model;

/** Vorlagen und Läufe für die Prozedur-Tests der Phase 176. */
trait BuildsProcedureRuns {
    protected function lead(): User {
        $user = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $user->organization);

        return $user;
    }

    protected function colleagueOf(User $user, string $state = 'teamleitung'): User {
        return User::factory()->{$state}()->create(['organization_id' => $user->organization_id]);
    }

    /**
     * @param  list<array<string, mixed>>  $steps  je Schritt mindestens `code`; Typ und Bezeichnung haben Vorgaben
     * @param  array<string, mixed>  $version  Metadaten der Version (risk_level, applicability, allow_blocked)
     */
    protected function draftTemplate(User $user, array $steps, array $version = []): ProcedureTemplate {
        $templates = app(ProcedureTemplateService::class);
        $template = $templates->create($user->organization, $user, ['code' => 'P176-' . uniqid(), 'name' => 'Prüfablauf']);
        $draft = $template->versions()->firstOrFail();
        if ($version !== []) {
            $templates->updateVersion($draft, $version);
        }
        foreach ($steps as $step) {
            $templates->addStepDef($draft, $step + ['step_type' => ProcedureStepType::Confirm->value, 'label' => 'Schritt ' . $step['code']]);
        }

        return $template;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $version
     */
    protected function publishedTemplate(User $user, array $steps, array $version = []): ProcedureTemplate {
        $template = $this->draftTemplate($user, $steps, $version);
        app(ProcedureTemplateService::class)->publish($this->draftOf($template), $user);

        return $template->fresh();
    }

    protected function draftOf(ProcedureTemplate $template): ProcedureTemplateVersion {
        return $template->versions()->whereNull('published_at')->firstOrFail();
    }

    protected function startRun(ProcedureTemplate $template, User $user, ?Model $subject = null): ProcedureRun {
        $subject ??= DiaryEntry::factory()->create(['organization_id' => $user->organization_id, 'user_id' => $user->id]);

        return app(ProcedureExecutionService::class)->start($template, $subject, $user);
    }

    protected function stepOf(ProcedureRun $run, string $code): ProcedureStepRun {
        return $run->stepRuns()->with('stepDef')->get()->firstOrFail(fn (ProcedureStepRun $stepRun): bool => $stepRun->stepDef?->code === $code);
    }
}
