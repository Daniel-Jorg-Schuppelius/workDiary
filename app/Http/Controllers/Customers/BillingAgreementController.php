<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingAgreementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBillingAgreementRequest;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\Customer;
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{DB, Gate};

/**
 * Sonderkonditions-Profil an der Kundenakte (Feature 098): Anlegen/Ändern
 * inkl. Satzzeilen; Satzänderungen werden sofort auf offene Monate angewendet
 * (reapplyRates — manuelle Overrides bleiben unberührt).
 */
class BillingAgreementController extends Controller {
    /** Modal-Fragment für Anlegen/Bearbeiten (data-entry-modal-trigger). */
    public function edit(Customer $customer): \Illuminate\View\View {
        Gate::authorize('update', $customer);

        $agreement = $customer->billingAgreement()->with('rates')->first();
        $categories = \App\Models\ActivityCategory::query()->active()->orderBy('label')->get();

        return view('customers.billing._agreement_form_dialog', [
            'customer' => $customer,
            'agreement' => $agreement,
            'activityCategories' => $categories,
            // Für die Anfahrt nur Kategorien anbieten, die an den Zeiten dieses
            // Kunden wirklich vorkommen (plus bereits gewählte): der Katalog
            // führt sonst interne Arten (Pause, Krank …), die auf einem
            // Kundenprojekt nie auftauchen — die Auswahl liefe ins Leere.
            'travelCategoryOptions' => $categories->whereIn(
                'id',
                $this->usedCategoryIds($customer)
                    ->merge($agreement === null ? [] : $agreement->travel_categories ?? [])
                    ->unique()->all()
            )->values(),
        ]);
    }

    /**
     * Tätigkeitskategorien, die an Zeiteinträgen dieses Kunden vorkommen.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function usedCategoryIds(Customer $customer): \Illuminate\Support\Collection {
        return \App\Models\TimeEntry::query()
            ->whereHas('project', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereNotNull('activity_category_id')
            ->distinct()
            ->pluck('activity_category_id')
            ->map(fn ($id): int => (int) $id);
    }

    public function save(
        SaveBillingAgreementRequest $request,
        Customer $customer,
        CustomerAccountStatementService $statements
    ): RedirectResponse {
        Gate::authorize('update', $customer);

        DB::transaction(function () use ($request, $customer): void {
            /** @var CustomerBillingAgreement $agreement */
            $agreement = CustomerBillingAgreement::query()->updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'organization_id' => $customer->organization_id,
                    'mode' => $request->validated('mode'),
                    'currency' => $request->validated('currency'),
                    'expected_monthly_amount' => $request->validated('expected_monthly_amount'),
                    'workdays_per_week' => (int) $request->validated('workdays_per_week'),
                    'travel_minutes_per_entry' => (int) ($request->validated('travel_minutes_per_entry') ?? 0),
                    'travel_categories' => array_values(array_map(
                        'intval',
                        (array) $request->validated('travel_categories', [])
                    )),
                    'holidays_as_weekend' => $request->boolean('holidays_as_weekend'),
                    'opening_balance' => (float) ($request->validated('opening_balance') ?? 0),
                    'opening_balance_date' => $request->validated('opening_balance_date'),
                    'active' => $request->boolean('active'),
                    'notes' => $request->validated('notes'),
                ],
            );

            // Satzzeilen anhand ihrer fachlichen Identität (Kategorie × Tagtyp)
            // fortschreiben statt sie zu ersetzen: an der Zeile hängt per
            // nullOnDelete der Konditionsnachweis der Zeiteinträge, und
            // reapplyRates erkennt konditionsbewertete Einträge nur an diesem
            // Marker — ein Löschen ließe jede Satzänderung wirkungslos
            // verpuffen. Historische Zeilen (valid_from gesetzt, aus DB/Import)
            // verwaltet der Dialog weiterhin nicht.
            $keptIds = [];
            foreach ($request->rateRows() as $row) {
                $keptIds[] = $this->upsertRate($agreement, $row)->id;
            }
            $agreement->rates()
                ->whereNull('valid_from')
                ->whereNotIn('id', $keptIds)
                ->delete();
        });

        $agreement = $customer->billingAgreement()->firstOrFail();
        if ($agreement->keepsLedger()) {
            $statements->reapplyRates($agreement);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_agreement_saved'));
    }

    /**
     * Satzzeile je (Kategorie × Tagtyp) anlegen oder fortschreiben. Stornierte
     * Zeilen werden wiederbelebt statt neu angelegt — der Unique-Index
     * uq_cbr_scope kennt kein deleted_at.
     *
     * @param  array{activity_category_id: int|null, day_type: string, hourly_rate: float}  $row
     */
    private function upsertRate(CustomerBillingAgreement $agreement, array $row): CustomerBillingRate {
        $rate = CustomerBillingRate::withTrashed()
            ->where('customer_billing_agreement_id', $agreement->id)
            ->where('activity_category_id', $row['activity_category_id'])
            ->where('day_type', $row['day_type'])
            ->whereNull('valid_from')
            ->first();

        if ($rate === null) {
            return CustomerBillingRate::create([
                'organization_id' => $agreement->organization_id,
                'customer_billing_agreement_id' => $agreement->id,
                'activity_category_id' => $row['activity_category_id'],
                'day_type' => $row['day_type'],
                'hourly_rate' => $row['hourly_rate'],
            ]);
        }

        $rate->fill(['hourly_rate' => $row['hourly_rate']]);
        $rate->trashed() ? $rate->restore() : $rate->save();

        return $rate;
    }
}
