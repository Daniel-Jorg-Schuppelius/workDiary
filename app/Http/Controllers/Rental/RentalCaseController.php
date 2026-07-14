<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCaseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Enums\Rental\RentalCaseStatus;
use App\Exceptions\{AssetNotUsableException, RentalConflictException};
use App\Http\Controllers\Controller;
use App\Models\{Asset, Customer, Project, User};
use App\Models\Rental\{RentalCase, RentalCaseAsset, RentalRateCard};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Rental\{RentalBillingService, RentalCaseService};
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Verleihakten (Feature 073, MVP-261/264): Liste mit Status/Fristen,
 * Akte mit Positionen, Protokollen, Konditionen und kaufmännischer Folge.
 * Anlegen/Bearbeiten als Dialog.
 */
class RentalCaseController extends Controller {
    public function __construct(
        private readonly RentalCaseService $service,
        private readonly RentalBillingService $billing,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', RentalCase::class);

        $statusFilter = RentalCaseStatus::tryFrom($request->string('status')->toString())?->value;
        $customerId = $request->filled('customer_id')
            ? Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'))
            : null;

        return view('rental.index', [
            'cases' => RentalCase::query()
                ->with(['customer', 'responsible', 'caseAssets.asset'])
                ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
                ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
                ->when($request->filled('overdue'), fn($q) => $q->where('status', RentalCaseStatus::Overdue->value))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'openCount' => RentalCase::query()->open()->count(),
            'overdueCount' => RentalCase::query()->where('status', RentalCaseStatus::Overdue->value)->count(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(RentalCase $rental): View {
        Gate::authorize('view', $rental);

        $rental->load([
            'customer', 'project', 'site', 'responsible', 'rateCard',
            'caseAssets.asset', 'caseAssets.replacedBy',
            'reservations.asset', 'handoverReports.asset', 'handoverReports.conditionItems',
            'handoverReports.accessoryItems', 'returnReports.asset',
            'returnReports.conditionItems', 'returnReports.accessoryItems',
            'charges.invoice', 'deposits', 'attachments',
        ]);

        return view('rental.show', [
            'case' => $rental,
            'chargeSuggestions' => Gate::allows('finance', $rental) ? $this->billing->suggestCharges($rental) : [],
            'rentableAssets' => $this->rentableAssets(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Anlege-Dialog (Formulare in Dialogen). */
    public function create(): View {
        Gate::authorize('create', RentalCase::class);

        return view('rental._form_dialog', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'rateCards' => RentalRateCard::query()->active()->orderBy('name')->get(['id', 'name', 'version']),
            'assets' => $this->rentableAssets(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        $actor = $request->user() ?? abort(401);
        $organization = $actor->organization ?? abort(403);
        $data = $this->validated($request);
        $assetIds = array_values(array_map('intval', (array) ($data['asset_ids'] ?? [])));
        unset($data['asset_ids']);

        $case = $this->service->open($organization, $actor, $data, $assetIds);

        return redirect()->route('rental.show', $case)
            ->with('status', __('Verleihakte :number angelegt.', ['number' => $case->number]));
    }

    public function update(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('update', $rental);

        if (! $rental->status->isOpen()) {
            return back()->withErrors(['status' => __('Abgeschlossene oder stornierte Akten sind unveränderlich.')]);
        }

        $data = $this->validated($request);
        unset($data['asset_ids']);
        $rental->fill($data)->save();

        if ($rental->wasChanged('rental_rate_card_id')) {
            $this->service->freezeTerms($rental);
        }

        return back()->with('status', __('Verleihakte aktualisiert.'));
    }

    /** Reservierung mit Konflikt- und Sperrprüfung (MVP-260). */
    public function reserve(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('update', $rental);

        try {
            $this->service->reserve($rental, $request->user() ?? abort(401));
        } catch (RentalConflictException|AssetNotUsableException|\RuntimeException $e) {
            return back()->withErrors(['reserve' => $e->getMessage()]);
        }

        return back()->with('status', __('Zeitraum reserviert — Verfügbarkeit ist blockiert.'));
    }

    /** Verlängerung (MVP-264) mit erneuter Konfliktprüfung. */
    public function extend(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('update', $rental);

        $data = $request->validate([
            'ends_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->extend($rental, $request->user() ?? abort(401), Carbon::parse($data['ends_at']), $data['reason'] ?? null);
        } catch (RentalConflictException|\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['ends_at' => $e->getMessage()]);
        }

        return back()->with('status', __('Laufzeit verlängert.'));
    }

    /** Tauschgerät (MVP-264). */
    public function swap(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('update', $rental);

        $request->merge([
            'case_asset_id' => Sqid::decodeOrNumeric(RentalCaseAsset::class, $request->input('case_asset_id')),
            'asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id')),
        ]);
        $data = $request->validate([
            'case_asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('rental_case_assets')],
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $current = RentalCaseAsset::query()->whereKey($data['case_asset_id'])->firstOrFail();
        $replacement = Asset::query()->whereKey($data['asset_id'])->firstOrFail();

        if ((int) $current->rental_case_id !== (int) $rental->id) {
            abort(404);
        }

        try {
            $this->service->swapAsset($rental, $current, $replacement, $request->user() ?? abort(401), $data['note'] ?? null);
        } catch (RentalConflictException|AssetNotUsableException|\RuntimeException $e) {
            return back()->withErrors(['asset_id' => $e->getMessage()]);
        }

        return back()->with('status', __('Tauschgerät dokumentiert.'));
    }

    public function cancel(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('update', $rental);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->service->cancel($rental, $request->user() ?? abort(401), $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Verleihakte storniert.'));
    }

    public function close(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('update', $rental);

        try {
            $this->service->close($rental, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Verleihakte abgeschlossen.'));
    }

    /** @return \Illuminate\Support\Collection<int, Asset> */
    private function rentableAssets(): \Illuminate\Support\Collection {
        return Asset::query()
            ->whereHas('rentalProfile', fn($q) => $q->where('is_rentable', true))
            ->with('rentalProfile')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $fieldModels = [
            'customer_id' => Customer::class,
            'project_id' => Project::class,
            'diary_entry_id' => \App\Models\DiaryEntry::class,
            'site_id' => \App\Models\Site::class,
            'responsible_user_id' => User::class,
            'rental_rate_card_id' => RentalRateCard::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }
        if ($request->filled('asset_ids')) {
            $request->merge([
                'asset_ids' => array_map(
                    fn($value) => Sqid::decodeOrNumeric(Asset::class, $value),
                    (array) $request->input('asset_ids'),
                ),
            ]);
        }

        return $request->validate([
            'customer_id' => ['required', 'integer', new ExistsInCurrentOrganization('customers')],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'diary_entry_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
            'site_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('sites')],
            'handover_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'rental_rate_card_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rental_rate_cards')],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:8000'],
            'asset_ids' => ['sometimes', 'array'],
            'asset_ids.*' => ['integer', new ExistsInCurrentOrganization('assets')],
        ]);
    }
}
