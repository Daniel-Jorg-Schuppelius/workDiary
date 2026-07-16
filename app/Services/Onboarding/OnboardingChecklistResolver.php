<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingChecklistResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Onboarding;

use App\Enums\Protocol\ProtocolStatus;
use App\Enums\User\UserRole;
use App\Models\{AuditLog, Classification, Customer, DiaryEntry, OnboardingProgress, Organization, Project, Protocol, TimeEntry, User, UserGroup};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OnboardingChecklistResolver {
    /** @var list<string> */
    private const OPERATOR_ROLE_NAMES = [
        UserRole::User->value,
        UserRole::Teamleitung->value,
        UserRole::Aussendienst->value,
        UserRole::Buchhaltung->value,
        UserRole::Callcenter->value,
        UserRole::TrainingManager->value,
    ];

    /** @var list<array{code:string, required:bool}> */
    private const STEPS = [
        ['code' => 'org.profile', 'required' => true],
        ['code' => 'org.branch_profile', 'required' => true],
        // optional (Feature 081): ohne Auswahl gilt „Voller Umfang"
        ['code' => 'org.scope', 'required' => false],
        // optional (Feature 082): ohne Auswahl gilt „Alles anzeigen"
        ['code' => 'org.workspaces', 'required' => false],
        ['code' => 'users.invite', 'required' => false],
        ['code' => 'roles.check', 'required' => true],
        ['code' => 'classification.check', 'required' => false],
        ['code' => 'customer.first', 'required' => true],
        ['code' => 'work.first', 'required' => true],
        ['code' => 'time.first', 'required' => false],
        ['code' => 'protocol.first_signed', 'required' => false],
        ['code' => 'backup.heartbeat', 'required' => false],
    ];

    /**
     * @return array{steps:list<array{code:string,title:string,required:bool,done:bool,state:string,skipped_reason:?string}>, required_done:int, required_total:int, progress_percent:int, all_required_done:bool}
     */
    public function forOrganization(Organization $organization, ?User $actor = null): array {
        $doneMap = $this->evaluate($organization);
        $now = CarbonImmutable::now();

        $existingRows = OnboardingProgress::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('step_code');

        $previouslyAllDone = $this->wasPreviouslyComplete($existingRows);

        $steps = array_map(
            function (array $step) use ($doneMap, $existingRows): array {
                $existing = $existingRows->get($step['code']);
                $manuallySkipped = $existing instanceof OnboardingProgress && $existing->state === 'skipped';
                $done = (bool) ($doneMap[$step['code']] ?? false);

                return [
                    'code' => $step['code'],
                    'title' => __('onboarding.step.' . $step['code'] . '.title'),
                    'required' => $step['required'],
                    'done' => $done,
                    'state' => $done ? 'done' : ($manuallySkipped ? 'skipped' : 'open'),
                    'skipped_reason' => $manuallySkipped ? $existing->skipped_reason : null,
                ];
            },
            self::STEPS
        );

        $requiredTotal = count(array_filter($steps, static fn(array $step): bool => $step['required']));
        $requiredDone = count(array_filter($steps, static fn(array $step): bool => $step['required'] && $step['done']));

        $this->syncProgressRows($organization, $steps, $existingRows, $actor, $now);

        $allRequiredDone = $requiredDone === $requiredTotal;
        if ($allRequiredDone && ! $previouslyAllDone) {
            $this->writeCompletedAudit($organization, $actor);
        }

        return [
            'steps' => $steps,
            'required_done' => $requiredDone,
            'required_total' => $requiredTotal,
            // max(1,…): STEPS enthält strukturell immer Pflichtschritte (PHPStan-Guard).
            'progress_percent' => (int) floor(($requiredDone * 100) / max(1, $requiredTotal)),
            'all_required_done' => $allRequiredDone,
        ];
    }

    /** @return array<string, bool> */
    private function evaluate(Organization $organization): array {
        $usersInOrg = User::query()->where('organization_id', $organization->id);

        $adminExists = $this->organizationHasAnyRole($organization->id, [UserRole::Admin->value]);
        $operatorExists = $this->organizationHasAnyRole($organization->id, self::OPERATOR_ROLE_NAMES);

        return [
            'org.profile' => filled($organization->name) && filled($organization->locale) && filled($organization->timezone),
            'org.branch_profile' => $this->organizationHasBranchProfile($organization),
            'org.scope' => $this->organizationHasConfiguredScope($organization),
            'org.workspaces' => $this->organizationHasConfiguredWorkspaces($organization),
            'users.invite' => $usersInOrg->count() >= 2,
            'roles.check' => $adminExists && $operatorExists,
            'classification.check' => Classification::query()->where('organization_id', $organization->id)->exists(),
            'customer.first' => Customer::query()->where('organization_id', $organization->id)->exists(),
            'work.first' => Project::query()->where('organization_id', $organization->id)->exists()
                || DiaryEntry::query()->where('organization_id', $organization->id)->exists(),
            'time.first' => TimeEntry::query()->where('organization_id', $organization->id)->exists(),
            'protocol.first_signed' => Protocol::query()
                ->where('organization_id', $organization->id)
                ->where('status', ProtocolStatus::Signed->value)
                ->exists(),
            'backup.heartbeat' => AuditLog::query()
                ->where('organization_id', $organization->id)
                ->whereIn('event', ['backup.completed', 'backup.succeeded'])
                ->where('created_at', '>=', now()->subHours(26))
                ->exists(),
        ];
    }

    /**
     * Funktionsumfang bewusst gewählt (Feature 081)? Über settings.scope_configured_at
     * oder — bei Bestands-Orgs — einen früheren Scope-Audit-Eintrag.
     */
    private function organizationHasConfiguredScope(Organization $organization): bool {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        if (filled($settings['scope_configured_at'] ?? null)) {
            return true;
        }

        return AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'license.scopeConfigured')
            ->exists();
    }

    /**
     * Arbeitsbereiche bewusst kuratiert (Feature 082)? Sobald settings.nav_focus_*
     * gesetzt ist. Rein optional.
     */
    private function organizationHasConfiguredWorkspaces(Organization $organization): bool {
        $settings = is_array($organization->settings) ? $organization->settings : [];

        return filled($settings['nav_focus_configured_at'] ?? null)
            || filled($settings['nav_focus_available'] ?? null)
            || filled($settings['nav_focus_default'] ?? null);
    }

    private function organizationHasBranchProfile(Organization $organization): bool {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $branchProfile = (string) ($settings['branch_profile_code'] ?? '');

        if ($branchProfile !== '') {
            return true;
        }

        return AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'branch_profile.installed')
            ->exists();
    }

    /** @param list<string> $roleNames */
    private function organizationHasAnyRole(int $organizationId, array $roleNames): bool {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        $directRoleExists = DB::table($modelHasRolesTable)
            ->join($rolesTable, $rolesTable . '.id', '=', $modelHasRolesTable . '.role_id')
            ->join('users', 'users.id', '=', $modelHasRolesTable . '.' . $morphKey)
            ->where($modelHasRolesTable . '.model_type', User::class)
            ->where('users.organization_id', $organizationId)
            ->whereIn($rolesTable . '.name', $roleNames)
            ->where(function ($query) use ($rolesTable, $teamKey, $organizationId): void {
                $query->where($rolesTable . '.' . $teamKey, $organizationId)
                    ->orWhereNull($rolesTable . '.' . $teamKey);
            })
            ->exists();

        if ($directRoleExists) {
            return true;
        }

        return DB::table($modelHasRolesTable)
            ->join($rolesTable, $rolesTable . '.id', '=', $modelHasRolesTable . '.role_id')
            ->join('user_groups', 'user_groups.id', '=', $modelHasRolesTable . '.' . $morphKey)
            ->join('user_user_group', 'user_user_group.user_group_id', '=', 'user_groups.id')
            ->join('users', 'users.id', '=', 'user_user_group.user_id')
            ->where($modelHasRolesTable . '.model_type', UserGroup::class)
            ->where('user_groups.organization_id', $organizationId)
            ->where('users.organization_id', $organizationId)
            ->whereIn($rolesTable . '.name', $roleNames)
            ->where(function ($query) use ($rolesTable, $teamKey, $organizationId): void {
                $query->where($rolesTable . '.' . $teamKey, $organizationId)
                    ->orWhereNull($rolesTable . '.' . $teamKey);
            })
            ->exists();
    }

    /**
     * @param  list<array{code:string,title:string,required:bool,done:bool,state:string,skipped_reason:?string}>  $steps
     * @param  \Illuminate\Support\Collection<string, OnboardingProgress>  $existingRows
     */
    private function syncProgressRows(Organization $organization, array $steps, $existingRows, ?User $actor, CarbonImmutable $now): void {
        foreach ($steps as $step) {
            $existing = $existingRows->get($step['code']);

            // Manuell übersprungene Schritte nicht überschreiben, solange die Bedingung offen ist.
            if ($existing instanceof OnboardingProgress && $existing->state === 'skipped' && ! $step['done']) {
                continue;
            }

            if ($step['done']) {
                $previousState = $existing?->state;
                $doneAt = $existing !== null ? ($existing->done_at ?? $now) : $now;

                OnboardingProgress::query()->withoutGlobalScopes()->updateOrCreate(
                    ['organization_id' => $organization->id, 'step_code' => $step['code']],
                    [
                        'state' => 'done',
                        'done_at' => $doneAt,
                        'done_by_user_id' => $existing !== null ? ($existing->done_by_user_id ?? $actor?->id) : $actor?->id,
                        'skipped_reason' => null,
                    ]
                );

                if ($previousState !== 'done') {
                    $this->writeStepCompletedAudit($organization, $step['code'], $actor);
                }

                continue;
            }

            OnboardingProgress::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organization->id, 'step_code' => $step['code']],
                [
                    'state' => 'open',
                    'done_at' => null,
                    'done_by_user_id' => null,
                ]
            );
        }
    }

    /** @param \Illuminate\Support\Collection<string, OnboardingProgress> $existingRows */
    private function wasPreviouslyComplete($existingRows): bool {
        $requiredCodes = array_map(static fn(array $s): string => $s['code'], array_filter(self::STEPS, static fn(array $s): bool => $s['required']));
        if ($existingRows->count() < count($requiredCodes)) {
            return false;
        }
        foreach ($requiredCodes as $code) {
            $row = $existingRows->get($code);
            if (! $row instanceof OnboardingProgress || $row->state !== 'done') {
                return false;
            }
        }

        return true;
    }

    private function writeStepCompletedAudit(Organization $organization, string $stepCode, ?User $actor): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'onboarding.stepCompleted',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'step_code' => $stepCode,
            ],
        ]);
    }

    private function writeCompletedAudit(Organization $organization, ?User $actor): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'onboarding.completed',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [],
        ]);
    }
}
