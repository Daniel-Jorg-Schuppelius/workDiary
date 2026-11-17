<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeConversionCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Period\PeriodKind;
use App\Enums\Sales\LeadIntakeStage;
use App\Models\Classification\{Classification, EntryType};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets, CustomerServiceManager};
use App\Services\Fields\FieldValues;
use App\Services\Sales\Contracts\LeadConversionCheck;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Aufnahmeakte an der Lead-Umwandlung (Feature 200, MVP-1641/1675).
 *
 * Sperre: Ist ein Paket mit Entscheidungspflicht aktiv — eingeschaltet in der
 * Organisation oder an der geöffneten Akte —, wird der Lead erst nach der
 * Annahme (und dem zweiten Blick, falls verlangt) umgewandelt. Ohne solches
 * Paket bleibt die Umwandlung wie bisher, auch mit geöffneter Akte.
 *
 * Übernahme an den Kunden aus der angenommenen Akte: Leistungsumfang aus
 * „gewünschte Leistungen“ (Feature 197, `customer_services`), Klassifikationen
 * (Pflegegrad), Fristmerkmale je eingeschaltetem Regelsatz (Grundteil und
 * Paketfelder) und Kundenfelder als Vorschlag (USt-IdNr., Zahlungsziel — nur
 * wenn am Kunden leer). Unvollständige Fristmerkmale werden gemeldet, nicht
 * erfunden.
 */
final class LeadIntakeConversionCheck implements LeadConversionCheck {
    public function __construct(
        private readonly LeadIntakeService $intakes,
        private readonly IntakePackages $packages,
        private readonly CustomerServiceManager $services,
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly CustomerDeadlineProfileService $profiles,
    ) {}

    public function blockingReason(Lead $lead): ?string {
        $intake = $this->intakes->current($lead);
        $organization = Organization::query()->find($lead->organization_id);
        $required = ($intake?->requiresDecision() ?? false)
            || ($organization instanceof Organization && $this->packages->requiresDecision($organization));
        if (! $required || ($intake !== null && $intake->isAccepted())) {
            return null;
        }

        return (string) match (true) {
            $intake === null => __('lead_intake.block.no_intake'),
            $intake->stage === LeadIntakeStage::Rejected => __('lead_intake.block.rejected'),
            $intake->awaitsSecondReview() => __('lead_intake.block.second_review'),
            default => __('lead_intake.block.not_accepted'),
        };
    }

    public function converted(Lead $lead, Customer $customer, bool $newCustomer, User $actor): array {
        $intake = $this->intakes->current($lead);
        if ($intake === null || ! $intake->isAccepted()) {
            return [];
        }
        $organization = Organization::query()->findOrFail($lead->organization_id);
        $values = $intake->fieldDocumentValues();
        $notices = [];

        $this->transferServices($customer, $values, $actor);
        $this->transferClassifications($intake, $customer, $values);
        $this->transferCustomerFields($intake, $customer, $values);
        array_push($notices, ...$this->transferDeadlineTraits($intake, $organization, $customer, $values, $lead, $actor));

        LeadIntake::query()->withoutGlobalScopes()->where('lead_id', $lead->id)->update(['customer_id' => $customer->id]);
        if ($intake->stage === LeadIntakeStage::Setup) {
            $intake->forceFill(['stage' => LeadIntakeStage::Completed, 'customer_id' => $customer->id, 'completed_at' => Carbon::now(), 'updated_by' => $actor->id])->save();
            $intake->audit('lead_intake.completed', ['customer_id' => $customer->id, 'new_customer' => $newCustomer]);
        }

        return $notices;
    }

    /** Gewünschte Leistungen → Leistungsumfang ab heute (je Eintragsart einmal; vorhandene bleiben). */
    private function transferServices(Customer $customer, FieldValues $values, User $actor): void {
        $slugs = $values->get('services');
        if (! is_array($slugs) || $slugs === []) {
            return;
        }
        $present = $this->services->forCustomer($customer, CarbonImmutable::today())->pluck('entry_type_id')->all();
        $entryTypes = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->whereIn('slug', array_map('strval', $slugs))
            ->get();
        foreach ($entryTypes as $entryType) {
            if (in_array($entryType->id, $present, true)) {
                continue;
            }
            $required = is_array($entryType->requires_period) ? ($entryType->requires_period[0] ?? null) : null;
            $kind = is_string($required) ? (PeriodKind::tryFrom($required) ?? PeriodKind::Year) : PeriodKind::Year;
            $this->services->add($customer, $entryType, null, true, $kind, CarbonImmutable::today(), null, (string) __('lead_intake.transfer.service_note'), $actor);
            $present[] = $entryType->id;
        }
    }

    /** Klassifikation je Domäne aus dem Paketfeld (Wert = Code), etwa Pflegegrad `pg3`. */
    private function transferClassifications(LeadIntake $intake, Customer $customer, FieldValues $values): void {
        foreach ($intake->packageSnapshots() as $package) {
            foreach ($package->transfer['classifications'] ?? [] as $domain => $field) {
                $code = $values->get(IntakeKeys::package($package->key, $field));
                if (! is_scalar($code) || (string) $code === '') {
                    continue;
                }
                $classification = Classification::query()->withoutGlobalScopes()
                    ->where('organization_id', $customer->organization_id)
                    ->where('domain', $domain)
                    ->where('code', (string) $code)
                    ->first();
                if (! $classification instanceof Classification) {
                    continue;
                }
                $known = ClassificationDomain::tryFrom($domain);
                if ($known !== null) {
                    $customer->syncClassificationDomain($known, [(int) $classification->id]);
                } else {
                    $customer->classifications()->syncWithoutDetaching([(int) $classification->id]);
                }
            }
        }
    }

    /** Kundenfelder als Vorschlag — nur wenn am Kunden leer. */
    private function transferCustomerFields(LeadIntake $intake, Customer $customer, FieldValues $values): void {
        $fill = [];
        foreach ($intake->packageSnapshots() as $package) {
            foreach ($package->transfer['customer'] ?? [] as $column => $field) {
                $value = $values->get(IntakeKeys::package($package->key, $field));
                if ($value === null || $value === '' || $customer->getAttribute($column) !== null || isset($fill[$column])) {
                    continue;
                }
                $fill[$column] = match ($column) {
                    'payment_terms_days' => max(0, NumberHelper::toIntOrNull($value) ?? 0),
                    default => StringHelper::trimToNull(is_scalar($value) ? (string) $value : null),
                };
            }
        }
        $fill = array_filter($fill, static fn (mixed $value): bool => $value !== null);
        if ($fill !== []) {
            $customer->forceFill($fill)->save();
        }
    }

    /**
     * Fristmerkmale je eingeschaltetem Regelsatz: Felder des Grundteils
     * (`rs_<regelsatz>__<merkmal>`) und Zuordnungen der Pakete. Fehlt eine
     * Pflichtangabe des Regelsatzes, bleibt es bei einem Hinweis.
     *
     * @return list<string>
     */
    private function transferDeadlineTraits(LeadIntake $intake, Organization $organization, Customer $customer, FieldValues $values, Lead $lead, User $actor): array {
        $traits = [];
        foreach ($intake->fullSchema() as $field) {
            $pair = IntakeKeys::ruleSetOf($field->key);
            if ($pair !== null && $field->type->hasValue() && $values->has($field->key)) {
                $traits[$pair[0]][$pair[1]] = $values->get($field->key);
            }
        }
        foreach ($intake->packageSnapshots() as $package) {
            foreach ($package->transfer['deadline_traits'] ?? [] as $ruleSet => $mapping) {
                foreach ($mapping as $trait => $field) {
                    $key = IntakeKeys::package($package->key, $field);
                    if ($values->has($key)) {
                        $traits[$ruleSet][$trait] = $values->get($key);
                    }
                }
            }
        }

        $notices = [];
        foreach ($this->ruleSets->enabled($organization) as $key => $ruleSet) {
            $input = $traits[$key] ?? [];
            if (array_filter($input, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [] && $value !== false) === []) {
                continue;
            }
            $validator = Validator::make(['traits' => $input], $this->profiles->rules($ruleSet));
            if ($validator->fails()) {
                $notices[] = (string) __('lead_intake.transfer.traits_incomplete', ['rule_set' => $ruleSet->label()]);

                continue;
            }
            $responsible = $lead->responsible_user_id !== null ? User::query()->find($lead->responsible_user_id) : null;
            $this->profiles->save($customer, $ruleSet, $input, CarbonImmutable::today(), $responsible instanceof User ? $responsible : $actor, $actor);
        }

        return $notices;
    }
}
