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

use App\Models\Asset\{MaintenancePlanTemplate, Software};
use App\Models\Audit\AuditLog;
use App\Models\Classification\{Classification, ClassificationRequirement, EntryType, Tag};
use App\Models\Facility\{CleaningProfile, RoomRequirementTemplate};
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\SlaContract;
use App\Modules\ModuleRegistry;
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Support\MorphMap;
use CommonToolkit\Helper\FileSystem\File;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Installiert deklarative Branchenprofile pro Organisation.
 */
class BranchProfileInstaller {
    public function __construct(private readonly ?ModuleRegistry $modules = null) {}

    /** @return list<ProfileInstallStep> Installationsschritte der Module ({@see ProfileInstallStep}) */
    private function steps(): array {
        $modules = $this->modules ?? app(ModuleRegistry::class);

        return array_map(static fn(string $class): ProfileInstallStep => app($class), $modules->extensions(ProfileInstallStep::class));
    }

    /**
     * @return array{profile_code: string, version: int, created: array<string, int>, updated: array<string, int>, skipped: array<string, int>}
     */
    public function install(Organization $organization, string $profileCode, ?User $actor = null, bool $force = false): array {
        /** @var array<string, mixed> $profile */
        $profile = require database_path("data/branchprofiles/{$profileCode}.php");

        return $this->installProfile($organization, $profile, $actor, $force, $profileCode);
    }

    /**
     * Installiert ein Profil-Array direkt (Restpunkt 042: Marketplace-Import
     * hochgeladener JSON-Profile nutzt denselben Kern wie die mitgelieferten
     * Dateien). Prüft min_app_version, bevor irgendetwas geschrieben wird.
     *
     * @param array<string, mixed> $profile
     * @return array{profile_code: string, version: int, created: array<string, int>, updated: array<string, int>, skipped: array<string, int>}
     */
    public function installProfile(Organization $organization, array $profile, ?User $actor = null, bool $force = false, string $profileCode = ''): array {
        // Versionsguard (Restpunkt 042): Profile können eine Mindest-App-Version verlangen (neue Domänen/Felder).
        $minAppVersion = trim((string) ($profile['min_app_version'] ?? ''));
        if ($minAppVersion !== '' && version_compare((string) config('app.version', '0.0.0'), $minAppVersion, '<')) {
            throw new \RuntimeException(sprintf(
                'Profil benötigt WorkDiary >= %s (installiert: %s).',
                $minAppVersion,
                (string) config('app.version', '0.0.0'),
            ));
        }

        $counterTemplate = [
            'classifications' => 0,
            'entry_types' => 0,
            'classification_requirements' => 0,
            'tags' => 0,
            'maintenance_plan_templates' => 0,
            'sla_contracts' => 0,
            'cleaning_profiles' => 0,
            'software' => 0,
            'procedure_templates' => 0,
            'room_requirement_templates' => 0,
            'dataprotection_requirements' => 0,
            'qualifications' => 0,
            'training_courses' => 0,
            'training_requirements' => 0,
        ];
        $created = $counterTemplate;
        $updated = $counterTemplate;
        $skipped = $counterTemplate;

        // MVP-841: Übersetzungen der Labels aus der Beilage i18n/<code>.php
        // (Inline `label_i18n` je Zeile gewinnt) → classifications.label_i18n.
        $sidecarCode = (string) ($profile['code'] ?? $profileCode);
        $sidecarPath = database_path("data/branchprofiles/i18n/{$sidecarCode}.php");
        /** @var array<string, array<string, array<string, string>>> $sidecar */
        $sidecar = $sidecarCode !== '' && File::isFile($sidecarPath) ? (array) require $sidecarPath : [];

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
                $labelI18n = $this->labelTranslations($row, $sidecar, (string) $domain, $code);

                $existing = Classification::query()
                    ->where('organization_id', $organization->id)
                    ->where('domain', $domain)
                    ->where('code', $code)
                    ->first();

                if ($existing instanceof Classification) {
                    if ($force) {
                        $existing->update([
                            'label' => (string) ($row['label'] ?? $code),
                            'label_i18n' => $labelI18n,
                            'sort_order' => (int) ($row['sort_order'] ?? $sort),
                            'active' => true,
                        ]);
                        $updated['classifications']++;
                    } elseif ($labelI18n !== null && ! is_array($existing->label_i18n)) {
                        // Übersetzungen nachtragen, ohne lokale Labels anzufassen.
                        $existing->update(['label_i18n' => $labelI18n]);
                        $skipped['classifications']++;
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
                    'label_i18n' => $labelI18n,
                    'sort_order' => (int) ($row['sort_order'] ?? $sort),
                    'active' => true,
                ]);
                $created['classifications']++;
            }
        }

        // Profil-gekoppelte Default-Struktur-Typen (entry_type_defaults):
        // fehlende ergänzen, vorhandene nie anfassen — Nutzeranpassungen und
        // Löschungen überleben; bewusste (Re-)Installation legt deklarierte
        // Slugs wieder an.
        $entryTypeSlugs = array_values(array_filter(
            (array) Arr::get($profile, 'entry_type_defaults', []),
            'is_string'
        ));
        foreach (EntryTypeSeeder::profilesFor($entryTypeSlugs) as $sort => $attrs) {
            $slugExists = EntryType::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('slug', $attrs['slug'])
                ->exists();
            if ($slugExists) {
                $skipped['entry_types']++;

                continue;
            }

            EntryType::query()->withoutGlobalScopes()->create(
                array_merge($attrs, ['organization_id' => $organization->id, 'sort' => $sort])
            );
            $created['entry_types']++;
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

        // Modulabschnitte des Profils (MVP-863): Prozedurvorlagen u. a. installieren die Module selbst.
        foreach ($this->steps() as $step) {
            $result = $step->install($organization, (array) Arr::get($profile, $step->key(), []), $actor);
            $created[$step->key()] = ($created[$step->key()] ?? 0) + $result['created'];
            $skipped[$step->key()] = ($skipped[$step->key()] ?? 0) + $result['skipped'];
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

        // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): Profile liefern Katalog-Presets (aktiv/inaktiv, Label)
        // für die Compliance-Lückenanalyse. Manuell angepasste (source=manual) werden nie überschrieben.
        /** @var list<array<string, mixed>> $privacyRequirements */
        $privacyRequirements = (array) Arr::get($profile, 'dataprotection_requirements_seed', []);
        /** @var array<string, array{label?: string, category?: ?string}> $privacyDefaults */
        $privacyDefaults = (array) config('dataprotection.compliance.requirements', []);
        foreach ($privacyRequirements as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $default = $privacyDefaults[$key] ?? [];

            $existing = \App\Models\Privacy\PrivacyRequirement::query()
                ->where('organization_id', $organization->id)
                ->where('requirement_key', $key)
                ->first();

            $payload = [
                'label' => (string) ($row['label'] ?? $default['label'] ?? $key),
                'category' => $row['category'] ?? $default['category'] ?? null,
                'check_type' => (string) ($row['check_type'] ?? $key),
                'active' => (bool) ($row['active'] ?? true),
                'source' => 'profile',
            ];

            if ($existing instanceof \App\Models\Privacy\PrivacyRequirement) {
                if ($existing->source !== 'manual' && ($force || $existing->source === 'default')) {
                    $existing->update($payload);
                    $updated['dataprotection_requirements']++;
                } else {
                    $skipped['dataprotection_requirements']++;
                }

                continue;
            }

            \App\Models\Privacy\PrivacyRequirement::query()->create(array_merge([
                'organization_id' => $organization->id,
                'requirement_key' => $key,
            ], $payload));
            $created['dataprotection_requirements']++;
        }

        // Qualifikationen/Unterweisungen je Gewerk (Feature-MVP „Branchenprofile";
        // Vollaudit 2026-07, N13): idempotent über (org, name); bestehende
        // Einträge werden nie überschrieben (Stammdaten-Hoheit beim Nutzer).
        $qualifications = (array) Arr::get($profile, 'qualifications_seed', []);
        foreach ($qualifications as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $exists = \App\Models\Hr\Qualification::query()
                ->where('organization_id', $organization->id)
                ->where('name', $name)
                ->exists();
            if ($exists) {
                $skipped['qualifications']++;

                continue;
            }

            \App\Models\Hr\Qualification::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'abbreviation' => $row['abbreviation'] ?? null,
                'description' => $row['description'] ?? null,
                'is_active' => true,
                'created_by' => $actor?->id,
            ]);
            $created['qualifications']++;
        }

        // Schulungsvorschläge je Gewerk (Feature 145, MVP-727): Kurs im
        // Katalog plus Pflichtzuordnung je Rolle. Idempotent über (org, code)
        // bzw. (org, kurs, zielgruppe); Bestehendes wird nie überschrieben —
        // der Katalog gehört der Organisation. Aus den Zuordnungen entstehen
        // die Soll-Einträge erst beim Abgleich (TrainingAssignmentService).
        /** @var list<array<string, mixed>> $trainingSuggestions */
        $trainingSuggestions = (array) Arr::get($profile, 'training_suggestions', []);
        foreach ($trainingSuggestions as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($code === '' || $title === '') {
                continue;
            }

            $course = \App\Models\Training\TrainingCourse::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            if ($course instanceof \App\Models\Training\TrainingCourse) {
                $skipped['training_courses']++;
            } else {
                $course = \App\Models\Training\TrainingCourse::query()->create([
                    'organization_id' => $organization->id,
                    'code' => $code,
                    'title' => $title,
                    'provider_kind' => (string) ($row['provider_kind'] ?? 'internal'),
                    'provider_name' => isset($row['provider_name']) ? (string) $row['provider_name'] : null,
                    'duration_minutes' => isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : null,
                    'validity_months' => isset($row['validity_months']) ? (int) $row['validity_months'] : null,
                    'is_mandatory' => (bool) ($row['is_mandatory'] ?? true),
                    'legal_basis' => isset($row['legal_basis']) ? (string) $row['legal_basis'] : null,
                    'lead_days' => max(0, (int) ($row['lead_days'] ?? 30)),
                    'is_active' => true,
                    'source' => 'profile',
                    'created_by_user_id' => $actor?->id,
                ]);
                $course->versions()->create([
                    'organization_id' => $organization->id,
                    'version' => 1,
                    'label' => null,
                    'is_current' => true,
                ]);
                $created['training_courses']++;
            }

            /** @var list<string> $roles */
            $roles = array_values(array_filter((array) ($row['roles'] ?? []), 'is_string'));
            foreach ($roles as $role) {
                $exists = \App\Models\Training\TrainingRequirement::query()->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('training_course_id', $course->id)
                    ->where('subject_kind', 'role')
                    ->where('subject_key', $role)
                    ->exists();
                if ($exists) {
                    $skipped['training_requirements']++;

                    continue;
                }

                \App\Models\Training\TrainingRequirement::query()->create([
                    'organization_id' => $organization->id,
                    'training_course_id' => $course->id,
                    'subject_kind' => 'role',
                    'subject_key' => $role,
                    'first_due_days' => max(0, (int) ($row['first_due_days'] ?? 30)),
                    'is_active' => true,
                    'source' => 'profile',
                    'note' => null,
                ]);
                $created['training_requirements']++;
            }
        }

        $installedProfileCode = (string) ($profile['code'] ?? $profileCode);
        $profileVersion = (int) ($profile['version'] ?? 1);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        // MVP-839: Das zuerst installierte Profil ist das Hauptprofil; weitere
        // Installationen ändern es nicht (Wechsel nur über setPrimary()).
        if ((string) ($settings['branch_profile_code'] ?? '') === '') {
            $settings['branch_profile_code'] = $installedProfileCode;
        }
        // Restpunkt 042: angewandte Profilversion je Code — Grundlage der
        // Update-Erkennung auf der Katalogseite.
        $versions = is_array($settings['branch_profile_versions'] ?? null) ? $settings['branch_profile_versions'] : [];
        $versions[$installedProfileCode] = $profileVersion;
        $settings['branch_profile_versions'] = $versions;
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'branch_profile.installed',
            'auditable_type' => MorphMap::stableKey(Organization::class),
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

    /**
     * Wechselt das Hauptprofil (MVP-839). Nur installierte Profile kommen in
     * Frage; Nav-Fokus und Fach-Defaults folgen dem Hauptprofil.
     */
    public function setPrimary(Organization $organization, string $profileCode, ?User $actor = null): void {
        if (! in_array($profileCode, $organization->installedBranchProfileCodes(), true)) {
            throw new \InvalidArgumentException(sprintf('Profil „%s" ist nicht installiert.', $profileCode));
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $previous = (string) ($settings['branch_profile_code'] ?? '');
        if ($previous === $profileCode) {
            return;
        }

        $settings['branch_profile_code'] = $profileCode;
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'branch_profile.primaryChanged',
            'auditable_type' => MorphMap::stableKey(Organization::class),
            'auditable_id' => $organization->id,
            'changes' => ['from' => $previous === '' ? null : $previous, 'to' => $profileCode],
            'ip' => null,
            'user_agent' => null,
        ]);
    }

    /**
     * Deinstalliert ein Profil (MVP-839, P12-09) — entfernt nur, was niemand
     * benutzt: Klassifikationen mit Verweisen werden deaktiviert, unbenutzte
     * gelöscht; Pflichtregeln des Profils fallen weg; Tags ohne Verwendung
     * fallen weg. Vorlagen (Prozeduren, Wartungspläne, SLA, Reinigung,
     * Raumanforderungen, Software, Qualifikationen, Schulungen, Datenschutz)
     * bleiben als Stammdaten der Organisation erhalten. Danach ist der Code
     * nicht mehr registriert; war er Hauptprofil, rückt das nächste nach.
     *
     * @return array{profile_code: string, removed: array<string, int>, deactivated: array<string, int>, kept: list<string>, primary: ?string}
     */
    public function uninstall(Organization $organization, string $profileCode, ?User $actor = null): array {
        if (! in_array($profileCode, $organization->installedBranchProfileCodes(), true)) {
            throw new \InvalidArgumentException(sprintf('Profil „%s" ist nicht installiert.', $profileCode));
        }

        $path = database_path("data/branchprofiles/{$profileCode}.php");
        /** @var array<string, mixed> $profile Importierte Marketplace-Profile ohne Datei: nur abmelden. */
        $profile = File::isFile($path) ? require $path : [];

        $removed = ['classifications' => 0, 'classification_requirements' => 0, 'tags' => 0];
        $deactivated = ['classifications' => 0];
        $keptTags = 0;

        /** @var array<string, list<array<string, mixed>>> $classificationDomains */
        $classificationDomains = (array) Arr::get($profile, 'classifications', []);
        foreach ($classificationDomains as $domain => $rows) {
            foreach ($rows as $row) {
                $code = (string) ($row['code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $classification = Classification::query()
                    ->where('organization_id', $organization->id)
                    ->where('domain', $domain)
                    ->where('code', $code)
                    ->first();
                if (! $classification instanceof Classification) {
                    continue;
                }
                if ($this->classificationIsReferenced($classification)) {
                    if ($classification->active) {
                        $classification->update(['active' => false, 'deprecated_at' => now()]);
                        $deactivated['classifications']++;
                    }

                    continue;
                }
                $classification->delete();
                $removed['classifications']++;
            }
        }

        /** @var list<array<string, mixed>> $requirements */
        $requirements = (array) Arr::get($profile, 'classification_requirements', []);
        foreach ($requirements as $row) {
            $deleted = ClassificationRequirement::query()
                ->where('organization_id', $organization->id)
                ->where('entry_type_code', (string) ($row['entry_type_code'] ?? ''))
                ->where('required_domain', (string) ($row['required_domain'] ?? ''))
                ->where('enforce_phase', (string) ($row['enforce_phase'] ?? ''))
                ->delete();
            $removed['classification_requirements'] += $deleted;
        }

        /** @var list<string> $tags */
        $tags = (array) Arr::get($profile, 'tags_seed', []);
        foreach ($tags as $tagName) {
            $name = trim((string) $tagName);
            if ($name === '') {
                continue;
            }
            $tag = Tag::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
            if (! $tag instanceof Tag) {
                continue;
            }
            if (DB::table('taggables')->where('tag_id', $tag->id)->exists()) {
                $keptTags++;

                continue;
            }
            $tag->delete();
            $removed['tags']++;
        }

        $kept = [];
        foreach (['procedure_templates', 'maintenance_plans_seed', 'sla_contracts_seed', 'cleaning_profiles_seed', 'room_requirement_templates_seed', 'software_seed', 'qualifications_seed', 'training_suggestions', 'dataprotection_requirements_seed'] as $key) {
            if ((array) Arr::get($profile, $key, []) !== []) {
                $kept[] = $key;
            }
        }
        if ($keptTags > 0) {
            $kept[] = 'tags_in_use';
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $versions = is_array($settings['branch_profile_versions'] ?? null) ? $settings['branch_profile_versions'] : [];
        unset($versions[$profileCode]);
        $settings['branch_profile_versions'] = $versions;
        $primary = (string) ($settings['branch_profile_code'] ?? '');
        if ($primary === $profileCode) {
            $next = array_key_first($versions);
            $primary = is_string($next) ? $next : '';
            if ($primary === '') {
                unset($settings['branch_profile_code']);
            } else {
                $settings['branch_profile_code'] = $primary;
            }
        }
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'branch_profile.uninstalled',
            'auditable_type' => MorphMap::stableKey(Organization::class),
            'auditable_id' => $organization->id,
            'changes' => [
                'profile_code' => $profileCode,
                'removed' => $removed,
                'deactivated' => $deactivated,
                'kept' => $kept,
                'primary' => $primary === '' ? null : $primary,
            ],
            'ip' => null,
            'user_agent' => null,
        ]);

        return [
            'profile_code' => $profileCode,
            'removed' => $removed,
            'deactivated' => $deactivated,
            'kept' => $kept,
            'primary' => $primary === '' ? null : $primary,
        ];
    }

    /**
     * Übersetzungen eines Labels: Beilage `i18n/<profil>.php` (Domäne → Code →
     * Sprache) plus Inline-`label_i18n` der Profilzeile (gewinnt). null = keine.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, array<string, string>>>  $sidecar
     * @return array<string, string>|null
     */
    private function labelTranslations(array $row, array $sidecar, string $domain, string $code): ?array {
        $fromFile = is_array($sidecar[$domain][$code] ?? null) ? $sidecar[$domain][$code] : [];
        $inline = is_array($row['label_i18n'] ?? null) ? $row['label_i18n'] : [];
        $merged = [];
        foreach ($inline + $fromFile as $locale => $label) {
            $locale = strtolower(trim((string) $locale));
            $label = trim((string) $label);
            if ($locale !== '' && $label !== '') {
                $merged[$locale] = $label;
            }
        }
        ksort($merged);

        return $merged === [] ? null : $merged;
    }

    /**
     * Verweist irgendein Fachobjekt auf die Klassifikation? Zuordnungen laufen
     * über das `classifiables`-Pivot (HasClassifications) und feste Spalten
     * (Zeiten, Produkte, Reklamationen, Import-Wertzuordnungen).
     */
    private function classificationIsReferenced(Classification $classification): bool {
        $id = (int) $classification->id;
        if (DB::table('classifiables')->where('classification_id', $id)->exists()) {
            return true;
        }

        $columns = [
            'time_entries' => ['rework_reason_classification_id', 'goodwill_reason_classification_id'],
            'products' => ['product_group_classification_id'],
            'claim_cases' => ['defect_type_classification_id', 'root_cause_classification_id', 'goodwill_reason_classification_id'],
            'import_value_mappings' => ['classification_id'],
        ];
        foreach ($columns as $table => $cols) {
            foreach ($cols as $column) {
                if (DB::table($table)->where($column, $id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
