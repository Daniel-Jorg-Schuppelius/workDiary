<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeTariffController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubFeeProration, ClubFeeTariffKind};
use App\Enums\Finance\RecurringInterval;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveFeeSurchargeRequest, SaveFeeTariffRateRequest, SaveFeeTariffRequest};
use App\Models\Club\{ClubDepartment, ClubFeeAccount, ClubFeeSurcharge, ClubFeeTariff, ClubFeeTariffRate};
use App\Services\Club\ClubFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Beitragstarife, Sätze und Abteilungszuschläge (Feature 159, MVP-849).
 * Rechte über die Beitragskonto-Policy; Fachlogik im ClubFeeService.
 */
class ClubFeeTariffController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubFeeService $fees,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);

        return view('club.fees.tariffs.index', [
            'tariffs' => ClubFeeTariff::query()->with('rates')->withCount('assignments')->orderBy('sort_order')->orderBy('name')->get(),
            'surcharges' => ClubFeeSurcharge::query()->with('department:id,name')->orderBy('name')->get(),
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.tariffs._tariff_dialog', ['tariff' => null, 'kinds' => ClubFeeTariffKind::cases()]);
    }

    public function store(SaveFeeTariffRequest $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->createTariff($this->currentOrganization(), $request->validated());

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.tariff_saved'));
    }

    public function edit(ClubFeeTariff $tariff): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.tariffs._tariff_dialog', ['tariff' => $tariff, 'kinds' => ClubFeeTariffKind::cases()]);
    }

    public function update(SaveFeeTariffRequest $request, ClubFeeTariff $tariff): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->updateTariff($tariff, $request->validated());

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.tariff_saved'));
    }

    public function destroy(ClubFeeTariff $tariff): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->deleteTariff($tariff);

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.tariff_deleted'));
    }

    // ── Sätze ────────────────────────────────────────────────────────────

    public function createRate(ClubFeeTariff $tariff): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.tariffs._rate_dialog', ['tariff' => $tariff, 'rate' => null] + $this->rateOptions());
    }

    public function storeRate(SaveFeeTariffRateRequest $request, ClubFeeTariff $tariff): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->saveRate($tariff, $request->validated());

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.rate_saved'));
    }

    public function editRate(ClubFeeTariffRate $rate): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.tariffs._rate_dialog', ['tariff' => $rate->tariff()->firstOrFail(), 'rate' => $rate] + $this->rateOptions());
    }

    public function updateRate(SaveFeeTariffRateRequest $request, ClubFeeTariffRate $rate): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->saveRate($rate->tariff()->firstOrFail(), $request->validated(), $rate);

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.rate_saved'));
    }

    public function destroyRate(ClubFeeTariffRate $rate): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->deleteRate($rate);

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.rate_deleted'));
    }

    // ── Zuschläge ────────────────────────────────────────────────────────

    public function createSurcharge(): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.tariffs._surcharge_dialog', ['surcharge' => null] + $this->surchargeOptions());
    }

    public function storeSurcharge(SaveFeeSurchargeRequest $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->saveSurcharge($this->currentOrganization(), $request->validated());

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.surcharge_saved'));
    }

    public function editSurcharge(ClubFeeSurcharge $surcharge): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.tariffs._surcharge_dialog', ['surcharge' => $surcharge] + $this->surchargeOptions());
    }

    public function updateSurcharge(SaveFeeSurchargeRequest $request, ClubFeeSurcharge $surcharge): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->saveSurcharge($this->currentOrganization(), $request->validated(), $surcharge);

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.surcharge_saved'));
    }

    public function destroySurcharge(ClubFeeSurcharge $surcharge): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->fees->deleteSurcharge($surcharge);

        return redirect()->route('club.fees.tariffs.index')->with('success', __('club.fees.flash.surcharge_deleted'));
    }

    /** @return array<string, mixed> */
    private function rateOptions(): array {
        return ['intervals' => RecurringInterval::cases(), 'prorations' => ClubFeeProration::cases()];
    }

    /** @return array<string, mixed> */
    private function surchargeOptions(): array {
        return [
            'intervals' => RecurringInterval::cases(),
            'departments' => ClubDepartment::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
