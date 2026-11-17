<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineProfileService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines;

use App\Models\Customer\{Customer, CustomerDeadlineProfile};
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Fields\{FieldDocument, FieldValidator, FieldValues};
use App\Support\{Formats, OrganizationContext};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fristmerkmale eines Kunden pflegen (Feature 197, MVP-1630) — einzige
 * Schreibstelle für `customer_deadline_profiles`. Eine Änderung legt eine neue
 * Fassung ab einem Stichtag an und schließt die geltende mit dem Vortag ab;
 * eine Fassung mit demselben Stichtag wird berichtigt. Danach rechnet der
 * Jahreslauf die Fristen des Kunden neu (nur offene).
 */
final class CustomerDeadlineProfileService {
    public function __construct(
        private readonly FieldValidator $validator,
        private readonly CustomerDeadlineGenerator $generator,
    ) {}

    /**
     * Prüfregeln der Merkmale unter `traits.<key>`.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(CustomerDeadlineRuleSet $ruleSet): array {
        return $this->validator->rules($ruleSet->traitSchema(), 'traits');
    }

    /** Am Tag gültige Fassung. */
    public function current(Customer $customer, string $ruleSet, ?CarbonInterface $day = null): ?CustomerDeadlineProfile {
        return $this->versions($customer, $ruleSet)
            ->first(static fn (CustomerDeadlineProfile $profile): bool => $profile->isValidOn($day ?? CarbonImmutable::today()));
    }

    /** Jüngste Fassung — Vorbelegung des Dialogs („wie Vorjahr“). */
    public function latest(Customer $customer, string $ruleSet): ?CustomerDeadlineProfile {
        return $this->versions($customer, $ruleSet)->last();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CustomerDeadlineProfile> */
    public function versions(Customer $customer, string $ruleSet): \Illuminate\Database\Eloquent\Collection {
        return CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('rule_set', $ruleSet)
            ->with('responsible:id,name')
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get();
    }

    /**
     * Neue Fassung ab $validFrom speichern (Werte bereits geprüft).
     *
     * @param  array<string, mixed>  $traits  Eingaben unter `traits`
     *
     * @throws ValidationException wenn schon eine spätere Fassung besteht
     */
    public function save(Customer $customer, CustomerDeadlineRuleSet $ruleSet, array $traits, CarbonImmutable $validFrom, ?User $responsible, User $actor): CustomerDeadlineProfile {
        $schema = $ruleSet->traitSchema();
        $document = new FieldDocument($schema, FieldValues::normalize($schema, $traits));
        $validFrom = $validFrom->startOfDay();

        $later = $this->versions($customer, $ruleSet->key())
            ->first(static fn (CustomerDeadlineProfile $profile): bool => $profile->valid_from->toDateString() > $validFrom->toDateString());
        if ($later !== null) {
            throw ValidationException::withMessages([
                'valid_from' => (string) __('customer_deadline.error.later_version', ['date' => $later->valid_from->format(Formats::date())]),
            ]);
        }

        $profile = DB::transaction(function () use ($customer, $ruleSet, $document, $validFrom, $responsible, $actor): CustomerDeadlineProfile {
            $same = $this->versions($customer, $ruleSet->key())
                ->first(static fn (CustomerDeadlineProfile $profile): bool => $profile->valid_from->toDateString() === $validFrom->toDateString());
            if ($same !== null) {
                $same->update(['traits' => $document, 'responsible_user_id' => $responsible?->id]);

                return $same->refresh();
            }

            $current = $this->current($customer, $ruleSet->key(), $validFrom);
            if ($current !== null) {
                $current->update(['valid_until' => $validFrom->subDay()->toDateString()]);
            }

            return CustomerDeadlineProfile::query()->create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'rule_set' => $ruleSet->key(),
                'traits' => $document,
                'valid_from' => $validFrom->toDateString(),
                'valid_until' => null,
                'responsible_user_id' => $responsible?->id,
                'created_by' => $actor->id,
            ]);
        });

        $this->resync($customer, $ruleSet);

        return $profile;
    }

    /** Kunde verlässt den Regelsatz: die geltende Fassung endet am Stichtag, offene Fristen danach entfallen beim Neurechnen. */
    public function end(Customer $customer, CustomerDeadlineRuleSet $ruleSet, CarbonImmutable $validUntil, User $actor): ?CustomerDeadlineProfile {
        $current = $this->current($customer, $ruleSet->key(), $validUntil);
        if ($current === null) {
            return null;
        }
        $current->update(['valid_until' => $validUntil->toDateString()]);
        $this->resync($customer, $ruleSet);

        return $current->refresh();
    }

    private function resync(Customer $customer, CustomerDeadlineRuleSet $ruleSet): void {
        $organization = $customer->organization;
        if ($organization === null) {
            $this->generator->syncCustomer($customer, $ruleSet);

            return;
        }
        OrganizationContext::run($organization, fn (): array => $this->generator->syncCustomer($customer, $ruleSet));
    }
}
