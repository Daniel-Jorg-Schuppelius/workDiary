<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileInstaller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Models\{AuditLog, Classification, ClassificationRequirement, CleaningProfile, MaintenancePlanTemplate, Organization, ProcedureTemplate, RoomRequirementTemplate, SlaContract, Software, Tag, User};
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Support\Arr;

/**
 * Installiert deklarative Branchenprofile pro Organisation.
 */
class BranchProfileInstaller {
    public function __construct(
        private readonly ?ProcedureTemplateService $procedures = null,
    ) {}

    private function procedureService(): ProcedureTemplateService {
        return $this->procedures ?? app(ProcedureTemplateService::class);
    }

    /**
     * @return array{profile_code: string, version: int, created: array<string, int>, updated: array<string, int>, skipped: array<string, int>}
     */
    public function install(Organization $organization, string $profileCode, ?User $actor = null, bool $force = false): array {
        /** @var array<string, mixed> $profile */
        $profile = require database_path("data/branchprofiles/{$profileCode}.php");

        $counterTemplate = [
            'classifications' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
            'maintenance_plan_templates' => 0,
            'sla_contracts' => 0,
            'cleaning_profiles' => 0,
            'software' => 0,
            'procedure_templates' => 0,
            'room_requirement_templates' => 0,
        ];
        $created = $counterTemplate;
        $updated = $counterTemplate;
        $skipped = $counterTemplate;

        /** @var array<string, list<array<string, mixed>>> $classificationDomains */
        $classificationDomains = (array) Arr::get($profile, 'classifications', []);
        foreach ($classificationDomains as $domain => $rows) {
            $sort = 0;
            foreach ($rows as $row) {
                $sort += 10;
                $code = (string) ($row['code'] ?? '');
                if ($code === '') {
                    continue;
                }

                $existing = Classification::query()
                    ->where('organization_id', $organization->id)
                    ->where('domain', $domain)
                    ->where('code', $code)
                    ->first();

                if ($existing instanceof Classification) {
                    if ($force) {
                        $existing->update([
                            'label' => (string) ($row['label'] ?? $code),
                            'sort_order' => (int) ($row['sort_order'] ?? $sort),
                            'active' => true,
                        ]);
                        $updated['classifications']++;
                    } else {
                        $skipped['classifications']++;
                    }

                    continue;
                }

                Classification::query()->create([
                    'organization_id' => $organization->id,
                    'domain' => $domain,
                    'code' => $code,
                    'label' => (string) ($row['label'] ?? $code),
                    'sort_order' => (int) ($row['sort_order'] ?? $sort),
                    'active' => true,
                ]);
                $created['classifications']++;
            }
        }

        /** @var list<array<string, mixed>> $requirements */
        $requirements = (array) Arr::get($profile, 'classification_requirements', []);
        foreach ($requirements as $row) {
            $entryTypeCode = (string) ($row['entry_type_code'] ?? '');
            $requiredDomain = (string) ($row['required_domain'] ?? '');
            $enforcePhase = (string) ($row['enforce_phase'] ?? '');
            if ($entryTypeCode === '' || $requiredDomain === '' || $enforcePhase === '') {
                continue;
            }

            $existing = ClassificationRequirement::query()
                ->where('organization_id', $organization->id)
                ->where('entry_type_code', $entryTypeCode)
                ->where('required_domain', $requiredDomain)
                ->where('enforce_phase', $enforcePhase)
                ->first();

            $payload = [
                'severity' => (string) ($row['severity'] ?? 'hard'),
                'allow_multi' => (bool) ($row['allow_multi'] ?? false),
                'min_count' => (int) ($row['min_count'] ?? 1),
                'max_count' => isset($row['max_count']) ? (int) $row['max_count'] : null,
                'only_if_json' => $row['only_if_json'] ?? null,
                'note' => isset($row['note']) ? (string) $row['note'] : null,
            ];

            if ($existing instanceof ClassificationRequirement) {
                if ($force) {
                    $existing->update($payload);
                    $updated['classification_requirements']++;
                } else {
                    $skipped['classification_requirements']++;
                }

                continue;
            }

            ClassificationRequirement::query()->create(array_merge([
                'organization_id' => $organization->id,
                'entry_type_code' => $entryTypeCode,
                'required_domain' => $requiredDomain,
                'enforce_phase' => $enforcePhase,
            ], $payload));
            $created['classification_requirements']++;
        }

        /** @var list<string> $tags */
        $tags = (array) Arr::get($profile, 'tags_seed', []);
        foreach ($tags as $tagName) {
            $name = trim((string) $tagName);
            if ($name === '') {
                continue;
            }

            $existing = Tag::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing instanceof Tag) {
                $skipped['tags']++;

                continue;
            }

            Tag::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'created_by' => $actor?->id,
            ]);
            $created['tags']++;
        }

        /** @var list<array<string, mixed>> $maintenancePlans */
        $maintenancePlans = (array) Arr::get($profile, 'maintenance_plans_seed', []);
        foreach ($maintenancePlans as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $existing = MaintenancePlanTemplate::query()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            $payload = [
                'label' => (string) ($row['label'] ?? $code),
                'asset_class' => $row['asset_class'] ?? null,
                'category_code' => $row['category_code'] ?? null,
                'interval_kind' => (string) ($row['interval_kind'] ?? 'months'),
                'interval_value' => max(1, (int) ($row['interval_value'] ?? 12)),
                'tolerance_days' => max(0, (int) ($row['tolerance_days'] ?? 0)),
                'procedure_template_code' => $row['procedure_template_code'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing instanceof MaintenancePlanTemplate) {
                if ($force) {
                    $existing->update($payload);
                    $updated['maintenance_plan_templates']++;
                } else {
                    $skipped['maintenance_plan_templates']++;
                }

                continue;
            }

            MaintenancePlanTemplate::query()->create(array_merge([
                'organization_id' => $organization->id,
                'code' => $code,
            ], $payload));
            $created['maintenance_plan_templates']++;
        }

        /** @var list<array<string, mixed>> $slaContracts */
        $slaContracts = (array) Arr::get($profile, 'sla_contracts_seed', []);
        foreach ($slaContracts as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $existing = SlaContract::query()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            $payload = [
                'customer_id' => $row['customer_id'] ?? null,
                'label' => (string) ($row['label'] ?? $code),
                'priority_table' => (array) ($row['priority_table'] ?? []),
                'business_hours' => $row['business_hours'] ?? null,
                'escalation_chain' => $row['escalation_chain'] ?? null,
                'is_default' => (bool) ($row['is_default'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing instanceof SlaContract) {
                if ($force) {
                    $existing->update($payload);
                    $updated['sla_contracts']++;
                } else {
                    $skipped['sla_contracts']++;
                }

                continue;
            }

            SlaContract::query()->create(array_merge([
                'organization_id' => $organization->id,
                'code' => $code,
            ], $payload));
            $created['sla_contracts']++;
        }

        /** @var list<array<string, mixed>> $cleaningProfiles */
        $cleaningProfiles = (array) Arr::get($profile, 'cleaning_profiles_seed', []);
        foreach ($cleaningProfiles as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $existing = CleaningProfile::query()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            $payload = [
                'label' => (string) ($row['label'] ?? $code),
                'interval_days' => isset($row['interval_days']) ? max(1, (int) $row['interval_days']) : null,
                'requirements' => isset($row['requirements']) ? (array) $row['requirements'] : null,
                'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing instanceof CleaningProfile) {
                if ($force) {
                    $existing->update($payload);
                    $updated['cleaning_profiles']++;
                } else {
                    $skipped['cleaning_profiles']++;
                }

                continue;
            }

            CleaningProfile::query()->create(array_merge([
                'organization_id' => $organization->id,
                'code' => $code,
                'created_by' => $actor?->id,
            ], $payload));
            $created['cleaning_profiles']++;
        }

        /** @var list<array<string, mixed>> $softwareSeed */
        $softwareSeed = (array) Arr::get($profile, 'software_seed', []);
        foreach ($softwareSeed as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $vendor = isset($row['vendor']) ? trim((string) $row['vendor']) : null;
            $vendor = $vendor === '' ? null : $vendor;

            $existing = Software::query()
                ->where('organization_id', $organization->id)
                ->where('name', $name)
                ->where(function ($q) use ($vendor) {
                    if ($vendor === null) {
                        $q->whereNull('vendor');
                    } else {
                        $q->where('vendor', $vendor);
                    }
                })
                ->first();

            $payload = [
                'vendor' => $vendor,
                'kind' => (string) ($row['kind'] ?? 'application'),
                'license_type' => isset($row['license_type']) ? (string) $row['license_type'] : null,
                'default_version' => isset($row['default_version']) ? (string) $row['default_version'] : null,
                'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing instanceof Software) {
                if ($force) {
                    $existing->update($payload);
                    $updated['software']++;
                } else {
                    $skipped['software']++;
                }

                continue;
            }

            Software::query()->create(array_merge([
                'organization_id' => $organization->id,
                'name' => $name,
                'created_by' => $actor?->id,
            ], $payload));
            $created['software']++;
        }

        /** @var list<array<string, mixed>> $procedureTemplates */
        $procedureTemplates = (array) Arr::get($profile, 'procedure_templates', []);
        foreach ($procedureTemplates as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $existing = ProcedureTemplate::query()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            // Vorlage existiert bereits (oder wurde lokal angepasst): idempotent
            // überspringen, niemals überschreiben (auch nicht bei force – eine
            // veröffentlichte Prozedurversion ist unveränderlich).
            if ($existing instanceof ProcedureTemplate) {
                $skipped['procedure_templates']++;

                continue;
            }

            // Vollständige Vorlage (Name/Schritte) wird nur installiert, wenn das
            // Profil sie deklarativ beschreibt UND ein Akteur vorhanden ist (die
            // Version benötigt einen Autor). Reine Code-Platzhalter ohne Schritte
            // werden als Folgearbeit übersprungen.
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            /** @var list<array<string, mixed>> $steps */
            $steps = (array) ($row['steps'] ?? []);
            if ($name === '' || $steps === [] || ! $actor instanceof User) {
                $skipped['procedure_templates']++;

                continue;
            }

            $service = $this->procedureService();
            $template = $service->create($organization, $actor, [
                'code' => $code,
                'name' => $name,
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'domain' => isset($row['domain']) ? (string) $row['domain'] : null,
                'active' => true,
            ]);

            $version = $template->versions()->firstOrFail();
            if (isset($row['risk_level'])) {
                $service->updateVersion($version, ['risk_level' => (string) $row['risk_level']]);
            }

            $normalizedSteps = [];
            foreach ($steps as $step) {
                $stepCode = (string) ($step['code'] ?? '');
                $stepType = (string) ($step['step_type'] ?? '');
                $stepLabel = (string) ($step['label'] ?? '');
                if ($stepCode === '' || $stepType === '' || $stepLabel === '') {
                    continue;
                }

                $normalizedSteps[] = [
                    'code' => $stepCode,
                    'step_type' => $stepType,
                    'label' => $stepLabel,
                    'description' => isset($step['description']) ? (string) $step['description'] : null,
                    'required' => (bool) ($step['required'] ?? true),
                    'blocking' => (bool) ($step['blocking'] ?? true),
                    'requires_second_person' => (bool) ($step['requires_second_person'] ?? false),
                    'requires_proof_type' => isset($step['requires_proof_type']) ? (string) $step['requires_proof_type'] : null,
                ];
            }

            $service->syncSteps($version, $normalizedSteps);
            $service->publish($version, $actor);

            $created['procedure_templates']++;
        }

        /** @var list<array<string, mixed>> $roomRequirementTemplates */
        $roomRequirementTemplates = (array) Arr::get($profile, 'room_requirement_templates_seed', []);
        foreach ($roomRequirementTemplates as $row) {
            $code = (string) ($row['code'] ?? '');
            $kind = (string) ($row['kind'] ?? '');
            if ($code === '' || $kind === '') {
                continue;
            }

            $existing = RoomRequirementTemplate::query()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            $payload = [
                'kind' => $kind,
                'label' => (string) ($row['label'] ?? $code),
                'level' => isset($row['level']) ? (string) $row['level'] : null,
                'note' => isset($row['note']) ? (string) $row['note'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($existing instanceof RoomRequirementTemplate) {
                if ($force) {
                    $existing->update($payload);
                    $updated['room_requirement_templates']++;
                } else {
                    $skipped['room_requirement_templates']++;
                }

                continue;
            }

            RoomRequirementTemplate::query()->create(array_merge([
                'organization_id' => $organization->id,
                'code' => $code,
                'created_by' => $actor?->id,
            ], $payload));
            $created['room_requirement_templates']++;
        }

        $installedProfileCode = (string) ($profile['code'] ?? $profileCode);
        $profileVersion = (int) ($profile['version'] ?? 1);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings['branch_profile_code'] = $installedProfileCode;
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'branch_profile.installed',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'profile_code' => $installedProfileCode,
                'version' => $profileVersion,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'force' => $force,
            ],
            'ip' => null,
            'user_agent' => null,
        ]);

        return [
            'profile_code' => $installedProfileCode,
            'version' => $profileVersion,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
