<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard};
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Gerätepool (MVP-259): Verleihprofile machen Assets leihfähig und tragen
 * Gerätegruppe, Pufferzeiten, Prüfpflicht-Gate, Zubehör und Standard-
 * Preisliste. Das Asset selbst bleibt im Asset-Modul führend.
 */
class RentalProfileController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', RentalCase::class);

        return view('rental.profiles', [
            'profiles' => RentalProfile::query()
                ->with(['asset.activeBlocks', 'defaultRateCard'])
                ->when($request->filled('group'), fn($q) => $q->where('group_code', $request->string('group')->toString()))
                ->orderByDesc('is_rentable')
                ->paginate(25)
                ->withQueryString(),
            'groups' => RentalProfile::query()->whereNotNull('group_code')->distinct()->orderBy('group_code')->pluck('group_code'),
            'assets' => Asset::query()
                ->whereDoesntHave('rentalProfile')
                ->orderBy('name')
                ->get(['id', 'name']),
            'rateCards' => RentalRateCard::query()->active()->orderBy('name')->get(['id', 'name', 'version']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        $data = $this->validated($request);
        $asset = Asset::query()->whereKey($data['asset_id'])->firstOrFail();

        $profile = RentalProfile::query()->updateOrCreate(
            ['organization_id' => $asset->organization_id, 'asset_id' => $asset->id],
            $data,
        );

        $asset->audit('rental.profileSaved', ['rentable' => $profile->is_rentable]);

        return back()->with('status', __('Verleihprofil gespeichert.'));
    }

    public function update(Request $request, RentalProfile $profile): RedirectResponse {
        Gate::authorize('create', RentalCase::class);

        $data = $this->validated($request);
        unset($data['asset_id']);
        $profile->fill($data)->save();

        return back()->with('status', __('Verleihprofil aktualisiert.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        foreach (['asset_id' => Asset::class, 'default_rate_card_id' => RentalRateCard::class] as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        if ($request->filled('accessories') && is_string($request->input('accessories'))) {
            $request->merge([
                'accessories' => array_values(array_filter(array_map('trim', explode("\n", (string) $request->input('accessories'))))),
            ]);
        }

        return $request->validate([
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'is_rentable' => ['sometimes', 'boolean'],
            'group_code' => ['nullable', 'string', 'max:60'],
            'home_site_label' => ['nullable', 'string', 'max:255'],
            'buffer_before_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'buffer_after_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'requires_inspection' => ['sometimes', 'boolean'],
            'min_condition' => ['nullable', 'string', 'max:20'],
            'accessories' => ['sometimes', 'array'],
            'accessories.*' => ['string', 'max:255'],
            'default_rate_card_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rental_rate_cards')],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
