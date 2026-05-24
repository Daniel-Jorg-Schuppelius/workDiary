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
use App\Models\{AuditLog, Classification, Customer, DiaryEntry, OnboardingProgress, Organization, Project, Protocol, TimeEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OnboardingChecklistResolver {
    /** @var list<array{code:string, title:string, required:bool}> */
    private const STEPS = [
        ['code' => 'org.profile', 'title' => 'Organisationsdaten vervollständigen', 'required' => true],
        ['code' => 'org.branch_profile', 'title' => 'Branchenprofil wählen', 'required' => true],
        ['code' => 'users.invite', 'title' => 'Erste Nutzer einladen', 'required' => false],
        ['code' => 'roles.check', 'title' => 'Rollen prüfen', 'required' => true],
        ['code' => 'classification.check', 'title' => 'Klassifikationen prüfen', 'required' => false],
        ['code' => 'customer.first', 'title' => 'Ersten Kunden anlegen', 'required' => true],
        ['code' => 'work.first', 'title' => 'Erstes Projekt oder Auftrag', 'required' => true],
        ['code' => 'time.first', 'title' => 'Erste Zeiterfassung', 'required' => false],
        ['code' => 'protocol.first_signed', 'title' => 'Erstes Protokoll signieren', 'required' => false],
        ['code' => 'backup.heartbeat', 'title' => 'Backup-Heartbeat', 'required' => false],
    ];

    /**
     * @return array{steps:list<array{code:string,title:string,required:bool,done:bool}>, required_done:int, required_total:int, progress_percent:int, all_required_done:bool}
     */
    public function forOrganization(Organization $organization, ?User $actor = null): array {
        $doneMap = $this->evaluate($organization);
        $now = CarbonImmutable::now();

        $steps = array_map(
            static fn(array $step): array => [
                'code' => $step['code'],
                'title' => $step['title'],
                'required' => $step['required'],
                'done' => (bool) ($doneMap[$step['code']] ?? false),
            ],
            self::STEPS
        );

        $requiredTotal = count(array_filter($steps, static fn(array $step): bool => $step['required']));
        $requiredDone = count(array_filter($steps, static fn(array $step): bool => $step['required'] && $step['done']));

        $this->syncProgressRows($organization, $steps, $actor, $now);

        return [
            'steps' => $steps,
            'required_done' => $requiredDone,
            'required_total' => $requiredTotal,
            'progress_percent' => $requiredTotal > 0 ? (int) floor(($requiredDone / $requiredTotal) * 100) : 100,
            'all_required_done' => $requiredDone === $requiredTotal,
        ];
    }

    /** @return array<string, bool> */
    private function evaluate(Organization $organization): array {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $branchProfile = (string) ($settings['branch_profile_code'] ?? '');

        $usersInOrg = User::query()->where('organization_id', $organization->id);

        $adminExists = $this->organizationHasRole($organization->id, UserRole::Admin->value);
        $operatorExists = $this->organizationHasRole($organization->id, UserRole::User->value);

        return [
            'org.profile' => filled($organization->name) && filled($organization->locale) && filled($organization->timezone),
            'org.branch_profile' => $branchProfile !== '',
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

    private function organizationHasRole(int $organizationId, string $roleName): bool {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        return DB::table($modelHasRolesTable)
            ->join($rolesTable, $rolesTable . '.id', '=', $modelHasRolesTable . '.role_id')
            ->join('users', 'users.id', '=', $modelHasRolesTable . '.' . $morphKey)
            ->where($modelHasRolesTable . '.model_type', User::class)
            ->where('users.organization_id', $organizationId)
            ->where($rolesTable . '.name', $roleName)
            ->where(function ($query) use ($rolesTable, $teamKey, $organizationId): void {
                $query->where($rolesTable . '.' . $teamKey, $organizationId)
                    ->orWhereNull($rolesTable . '.' . $teamKey);
            })
            ->exists();
    }

    /**
     * @param  list<array{code:string,title:string,required:bool,done:bool}>  $steps
     */
    private function syncProgressRows(Organization $organization, array $steps, ?User $actor, CarbonImmutable $now): void {
        foreach ($steps as $step) {
            /** @var OnboardingProgress|null $existing */
            $existing = OnboardingProgress::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('step_code', $step['code'])
                ->first();

            // Manuell auf "skipped" gesetzte Schritte werden nicht überschrieben,
            // solange die Bedingung noch nicht erfüllt ist.
            if ($existing instanceof OnboardingProgress && $existing->state === 'skipped' && ! $step['done']) {
                continue;
            }

            if ($step['done']) {
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
}
