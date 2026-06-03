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

use App\Models\{AuditLog, Classification, ClassificationRequirement, CleaningProfile, MaintenancePlanTemplate, Organization, SlaContract, Software, Tag, User};
use Illuminate\Support\Arr;

/**
 * Installiert deklarative Branchenprofile pro Organisation.
 */
class BranchProfileInstaller {
    /**
     * @return array{profile_code: string, version: int, created: array<string, int>, updated: array<string, int>, skipped: array<string, int>}
     */
    public function install(Organization $organization, string $profileCode, ?User $actor = null, bool $force = false): array {
        /** @var array<string, mixed> $profile */
        $profile = require database_path("data/branchprofiles/{$profileCode}.php");

        $created = [
            'classifications' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
            'maintenance_plan_templates' => 0,
            'sla_contracts' => 0,
            'cleaning_profiles' => 0,
            'software' => 0,
        ];
        $updated = [
            'classifications' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
            'maintenance_plan_templates' => 0,
            'sla_contracts' => 0,
            'cleaning_profiles' => 0,
            'software' => 0,
        ];
        $skipped = [
            'classifications' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
            'maintenance_plan_templates' => 0,
            'sla_contracts' => 0,
            'cleaning_profiles' => 0,
            'software' => 0,
        ];

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
