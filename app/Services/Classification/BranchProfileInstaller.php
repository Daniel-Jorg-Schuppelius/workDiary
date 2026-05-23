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

use App\Models\AuditLog;
use App\Models\Classification;
use App\Models\ClassificationRequirement;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\User;
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
        ];
        $updated = [
            'classifications' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
        ];
        $skipped = [
            'classifications' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
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

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'branch_profile.installed',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'profile_code' => (string) ($profile['code'] ?? $profileCode),
                'version' => (int) ($profile['version'] ?? 1),
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'force' => $force,
            ],
            'ip' => null,
            'user_agent' => null,
        ]);

        return [
            'profile_code' => (string) ($profile['code'] ?? $profileCode),
            'version' => (int) ($profile['version'] ?? 1),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
