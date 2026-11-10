<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelExpenseDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Travel\Demo;

use App\Enums\Expense\PaymentMethod;
use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Models\Travel\{Expense, ExpenseCategory, PerDiemDay, PerDiemTrip, TravelLog};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Expense\{ExpenseService, PerDiemRateLookup, PerDiemTripService};
use App\Services\Travel\TravelLogService;
use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * Reisen und Spesen zur Vorführung (MVP-1532): Auslagen in allen Stufen
 * (Entwurf, zur Genehmigung, genehmigt, erstattet), zwei Fahrten im
 * Fahrtenbuch zu Kunden der Musterbranche und eine Dienstreise mit
 * Verpflegungspauschale, sofern die Pauschalsätze eingespielt sind.
 */
final class TravelExpenseDemoBlock implements DemoBlock {
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly TravelLogService $travelLogs,
        private readonly PerDiemTripService $trips,
        private readonly PerDiemRateLookup $rates,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        $operatorA = $context->users->get(1);
        $operatorB = $context->users->get(2);
        $office = $context->users->get(3);
        /** @var Project|null $project */
        $project = $context->projects->first();
        $customer = $context->mainCustomer;
        if (! $context->moduleActive('module.spesen') || $admin === null || $operatorA === null || $operatorB === null || $office === null || $project === null || $customer === null) {
            return ['expenses' => 0, 'travel_logs' => 0, 'per_diem_trips' => 0];
        }
        $organization = $context->organization;
        $today = CarbonImmutable::now(Tz::ofOrganization($organization))->startOfDay();

        // Die Standardkategorien legt der OrganizationObserver an; fehlt eine, bleibt die Auslage ohne Kategorie.
        $categories = ExpenseCategory::query()->where('organization_id', $organization->id)->get()->keyBy('slug');

        /** @var list<array{name: string, city: string}> $places */
        $places = $context->blueprint['customers'];
        $city = $places[0]['city'];
        $secondCustomer = Customer::query()->where('organization_id', $organization->id)->where('name', $places[1]['name'] ?? '')->first() ?? $customer;
        $secondCity = $places[1]['city'] ?? $city;
        $pending = $this->expense($operatorA, $categories->get(ExpenseCategory::SLUG_PARKING), $customer, 'parking', $project, $today->subDays(3), '12.00', PaymentMethod::PrivatePaid, (string) __('demo.travel.expense.parking', ['city' => $city]));
        $this->expenses->submitForApproval($pending);

        $hotel = $this->expense($operatorB, $categories->get(ExpenseCategory::SLUG_LODGING), $customer, 'lodging', $project, $today->subDays(8), '109.00', PaymentMethod::PrivatePaid, (string) __('demo.travel.expense.hotel', ['city' => $city]));
        $this->expenses->approve($this->expenses->submitForApproval($hotel), $admin);

        $material = $this->expense($operatorA, $categories->get(ExpenseCategory::SLUG_SUPPLIES), $customer, 'supplies', $project, $today->subDays(12), '34.90', PaymentMethod::Cash, (string) __('demo.travel.expense.material'));
        $material = $this->expenses->approve($this->expenses->submitForApproval($material), $admin);
        $this->expenses->markReimbursed($material, 'DEMO-ERST-001');

        $this->expense($office, $categories->get(ExpenseCategory::SLUG_HOSPITALITY), $customer, 'hospitality', null, $today->subDays(1), '58.40', PaymentMethod::CompanyCard, (string) __('demo.travel.expense.hospitality'));

        $logs = 0;
        foreach ([
            [$operatorA, $customer, $city, $today->subDays(3)->setTime(7, 30), 45, '38.50'],
            [$operatorB, $secondCustomer, $secondCity, $today->subDays(8)->setTime(6, 15), 95, '112.00'],
        ] as [$driver, $destination, $destinationCity, $start, $minutes, $km]) {
            $this->travelLogs->create([
                'organization_id' => $organization->id,
                'user_id' => $driver->id,
                'customer_id' => $destination->id,
                'project_id' => $destination->is($customer) ? $project->id : null,
                'date' => $start->toDateString(),
                'started_at' => $start->format('Y-m-d H:i:s'),
                'ended_at' => $start->addMinutes($minutes)->format('Y-m-d H:i:s'),
                'from_address' => (string) __('demo.travel.from_address'),
                'to_address' => $destination->name . ', ' . $destinationCity,
                'distance_km' => $km,
                'round_trip' => true,
                'trip_kind' => TripKind::Business->value,
                'vehicle' => TravelLogVehicle::Private_->value,
                'reimbursable' => true,
                'purpose' => (string) __('demo.travel.trip_purpose', ['customer' => $destination->name]),
                'created_by' => $driver->id,
            ]);
            $logs++;
        }

        $perDiem = 0;
        $departure = $today->subDays(8)->setTime(6, 0);
        if ($this->rates->for('DE', $departure) !== null) {
            $this->trips->create([
                'organization_id' => $organization->id,
                'user_id' => $operatorB->id,
                'customer_id' => $secondCustomer->id,
                'country' => 'DE',
                'purpose' => (string) __('demo.travel.trip_purpose', ['customer' => $secondCustomer->name]),
                'location' => $secondCity,
                'started_at' => $departure->utc(),
                'ended_at' => $departure->addDay()->setTime(18, 30)->utc(),
                'accommodation_provided' => false,
                'created_by' => $operatorB->id,
            ]);
            $perDiem = 1;
        }

        return ['expenses' => 4, 'travel_logs' => $logs, 'per_diem_trips' => $perDiem];
    }

    public function purge(Organization $organization): void {
        $tripIds = PerDiemTrip::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('id');
        PerDiemDay::query()->whereIn('per_diem_trip_id', $tripIds)->delete();
        PerDiemTrip::query()->withoutGlobalScopes()->whereKey($tripIds)->delete();
        Expense::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNotNull('corrects_expense_id')->delete();
        Expense::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        TimeEntry::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNotNull('travel_log_id')->delete();
        // Demo-Mandant (is_demo): Demo-Fahrten sind keine Nachweise; GoBD-Ausnahme im Gate begründet.
        TravelLog::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNotNull('corrects_travel_log_id')->delete();
        TravelLog::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }

    private function expense(User $owner, ?ExpenseCategory $category, Customer $customer, string $vendor, ?Project $project, CarbonImmutable $date, string $gross, PaymentMethod $method, string $description): Expense {
        return $this->expenses->create([
            'organization_id' => $customer->organization_id,
            'user_id' => $owner->id,
            'expense_category_id' => $category?->id,
            'customer_id' => $customer->id,
            'project_id' => $project?->id,
            'date' => $date->toDateString(),
            'vendor' => (string) __('demo.travel.vendor.' . $vendor),
            'description' => $description,
            'payment_method' => $method,
            'amount_gross' => $gross,
            'created_by' => $owner->id,
        ]);
    }
}
