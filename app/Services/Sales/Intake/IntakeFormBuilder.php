<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeFormBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\Fields\FieldType;
use App\Models\Classification\EntryType;
use App\Models\Platform\Organization;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Fields\{FieldDefinition, FieldSchema, FieldValues};
use App\Services\Sales\Intake\Contracts\IntakeCheckPackage;
use App\Services\Sales\Intake\Dto\IntakePackageSnapshot;
use App\Services\Sales\Intake\Packages\ProfileIntakeCheckPackage;

/**
 * Setzt den Bogen einer neuen Akte zusammen (Feature 200, MVP-1641):
 *
 * 1. Grundteil „Anfrage und Gespräch“ — Anliegen, gewünschte Leistungen
 *    (Eintragsarten der Organisation; die Umwandlung legt daraus den
 *    Leistungsumfang an, Feature 197), gewünschter Beginn, Gesprächsnotizen.
 * 2. Fristmerkmale je eingeschaltetem Regelsatz der Kundenfristen (Feature
 *    197) — außer denen, die ein Paket selbst erhebt (Pflege: Leistungsbezug).
 * 3. Je Prüfpaket seine Felder und seine Unterlagenliste.
 *
 * Schema und Paketstand werden an der Akte eingefroren.
 */
final class IntakeFormBuilder {
    public const string BASE_SECTION = 'intake';

    public function __construct(private readonly CustomerDeadlineRuleSets $ruleSets) {}

    /**
     * @param  array<string, IntakeCheckPackage>  $packages
     * @return array{schema: FieldSchema, packages: list<IntakePackageSnapshot>}
     */
    public function build(Organization $organization, array $packages): array {
        $fields = $this->baseFields($organization);
        $covered = [];
        foreach ($packages as $package) {
            foreach ($package->transfer()['deadline_traits'] ?? [] as $ruleSet => $traits) {
                foreach (array_keys($traits) as $trait) {
                    $covered[$ruleSet][$trait] = true;
                }
            }
        }
        array_push($fields, ...$this->ruleSetFields($organization, $covered));

        $snapshots = [];
        foreach ($packages as $key => $package) {
            array_push($fields, ...$this->packageFields($package));
            $sensitive = [];
            foreach ($package->sensitiveFields() as $field => $kind) {
                $sensitive[IntakeKeys::package($key, $field)] = $kind;
            }
            $snapshots[] = new IntakePackageSnapshot(
                key: $key,
                label: $package->label(),
                requiresDecision: $package->requiredBeforeDecision(),
                retention: $package->retention(),
                // Profilpakete haben eine feste Frist; Klassenpakete rechnen sie bei der Annahme (GwG: je Risikostufe).
                refreshMonths: $package instanceof ProfileIntakeCheckPackage ? $package->refreshInterval(new FieldValues, $organization) : null,
                sensitive: $sensitive,
                transfer: $package->transfer(),
                isClass: ! $package instanceof ProfileIntakeCheckPackage,
            );
        }

        return ['schema' => new FieldSchema($fields), 'packages' => $snapshots];
    }

    /** @return list<FieldDefinition> */
    private function baseFields(Organization $organization): array {
        $fields = [
            new FieldDefinition(self::BASE_SECTION, FieldType::Section, (string) __('lead_intake.form.section_base')),
            new FieldDefinition('request', FieldType::Textarea, (string) __('lead_intake.form.request'), required: true),
        ];
        $entryTypes = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'slug', 'label']);
        $options = [];
        foreach ($entryTypes as $entryType) {
            $slug = (string) $entryType->slug;
            if ($slug !== '' && ! isset($options[$slug])) {
                $options[$slug] = (string) $entryType->label;
            }
        }
        if ($options !== []) {
            $fields[] = new FieldDefinition('services', FieldType::Multichoice, (string) __('lead_intake.form.services'),
                options: array_map('strval', array_keys($options)), help: (string) __('lead_intake.form.services_help'), optionLabels: $options);
        }
        $fields[] = new FieldDefinition('start_on', FieldType::Date, (string) __('lead_intake.form.start_on'));
        $fields[] = new FieldDefinition('interview_notes', FieldType::Textarea, (string) __('lead_intake.form.interview_notes'));

        return $fields;
    }

    /**
     * Fristmerkmale der eingeschalteten Regelsätze, ohne Pflicht (Pflicht prüft
     * der Regelsatz bei der Übernahme an den Kunden).
     *
     * @param  array<string, array<string, true>>  $covered
     * @return list<FieldDefinition>
     */
    private function ruleSetFields(Organization $organization, array $covered): array {
        $fields = [];
        foreach ($this->ruleSets->enabled($organization) as $key => $ruleSet) {
            $traits = [];
            $schema = $ruleSet->traitSchema();
            foreach ($schema as $trait) {
                if (! $trait->type->hasValue() || isset($covered[$key][$trait->key])) {
                    continue;
                }
                $condition = $trait->visibleIf;
                if ($condition !== null && $schema->has($condition['field'])) {
                    $condition['field'] = IntakeKeys::ruleSet($key, $condition['field']);
                }
                $row = $trait->with(required: false, visibleIf: $condition, replaceVisibleIf: true)->toArray();
                $row['key'] = IntakeKeys::ruleSet($key, $trait->key);
                $traits[] = FieldDefinition::fromArray($row);
            }
            if ($traits === []) {
                continue;
            }
            $fields[] = new FieldDefinition(IntakeKeys::ruleSet($key, 'section'), FieldType::Section,
                (string) __('lead_intake.form.section_traits', ['rule_set' => $ruleSet->label()]), help: (string) __('lead_intake.form.traits_help'));
            array_push($fields, ...$traits);
        }

        return $fields;
    }

    /** @return list<FieldDefinition> */
    private function packageFields(IntakeCheckPackage $package): array {
        $key = $package->key();
        $fields = IntakeKeys::prefixed($package->schema(), $key)->all();
        if ($fields === [] || $fields[0]->type->hasValue()) {
            array_unshift($fields, new FieldDefinition(IntakeKeys::package($key, IntakeKeys::PACKAGE_SECTION), FieldType::Section, $package->label()));
        }
        $documents = $package->documents();
        if ($documents !== []) {
            $fields[] = new FieldDefinition(IntakeKeys::package($key, IntakeKeys::DOCUMENTS_SECTION), FieldType::Section,
                (string) __('lead_intake.form.section_documents', ['package' => $package->label()]), help: (string) __('lead_intake.form.documents_help'));
            foreach ($documents as $document => $label) {
                $fields[] = new FieldDefinition(IntakeKeys::document($key, $document), FieldType::Boolean, $label);
            }
        }

        return $fields;
    }
}
