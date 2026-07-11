<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalHandoverController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Enums\Rental\{RentalCondition, RentalReturnFollowUp};
use App\Exceptions\AssetNotUsableException;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Rental\{RentalCase, RentalConditionItem};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Attachments\FileAttacher;
use App\Services\Rental\RentalCaseService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Übergabe- und Rücknahmeprotokolle (MVP-263/265): getrennte Protokolle mit
 * Zustand, Zubehör, Zählerstand/Betriebsstunden, Fotos und Unterschrift.
 * Die Rücknahme trägt die Folgeentscheidung (Reinigung/Sperre/Reklamation).
 */
class RentalHandoverController extends Controller {
    public function __construct(private readonly RentalCaseService $service) {}

    public function handover(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('handover', $rental);

        $data = $this->validated($request, forReturn: false);
        $asset = Asset::query()->whereKey($data['asset_id'])->firstOrFail();
        $actor = $request->user() ?? abort(401);

        try {
            $report = $this->service->handover($rental, $asset, $actor, $data);
        } catch (AssetNotUsableException|\RuntimeException $e) {
            return back()->withErrors(['asset_id' => $e->getMessage()]);
        }

        foreach ((array) $request->file('photos', []) as $photo) {
            app(FileAttacher::class)->store($report, $photo, $actor->id);
        }

        return back()->with('status', __('Übergabe protokolliert.'));
    }

    public function return(Request $request, RentalCase $rental): RedirectResponse {
        Gate::authorize('handover', $rental);

        $data = $this->validated($request, forReturn: true);
        $asset = Asset::query()->whereKey($data['asset_id'])->firstOrFail();
        $actor = $request->user() ?? abort(401);

        try {
            $report = $this->service->returnAsset($rental, $asset, $actor, $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['asset_id' => $e->getMessage()]);
        }

        foreach ((array) $request->file('photos', []) as $photo) {
            app(FileAttacher::class)->store($report, $photo, $actor->id);
        }

        return back()->with('status', __('Rücknahme protokolliert.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $forReturn): array {
        if ($request->filled('asset_id')) {
            $request->merge(['asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id'))]);
        }

        $rules = [
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'reported_at' => ['nullable', 'date'],
            'condition' => ['required', Rule::enum(RentalCondition::class)],
            'checklist' => ['sometimes', 'array'],
            'meter_value' => ['nullable', 'numeric', 'min:0'],
            'operating_hours' => ['nullable', 'numeric', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'signature_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
            'condition_items' => ['sometimes', 'array'],
            'condition_items.*.label' => ['required_with:condition_items', 'string', 'max:255'],
            'condition_items.*.state' => ['nullable', Rule::in(RentalConditionItem::STATES)],
            'condition_items.*.note' => ['nullable', 'string', 'max:1000'],
            'accessory_items' => ['sometimes', 'array'],
            'accessory_items.*.label' => ['required_with:accessory_items', 'string', 'max:255'],
            'accessory_items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'accessory_items.*.present' => ['nullable', 'boolean'],
            'accessory_items.*.note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['sometimes', 'array'],
            'photos.*' => FileAttacher::rule(),
        ];

        if ($forReturn) {
            $rules += [
                'damages' => ['nullable', 'string', 'max:4000'],
                'missing_parts' => ['nullable', 'string', 'max:4000'],
                'cleaning_required' => ['sometimes', 'boolean'],
                'consumables' => ['sometimes', 'array'],
                'follow_up' => ['required', Rule::enum(RentalReturnFollowUp::class)],
                'follow_up_note' => ['nullable', 'string', 'max:4000', 'required_if:follow_up,repair,block,claim'],
            ];
        }

        $data = $request->validate($rules);

        if ($data['signature_name'] ?? null) {
            $data['signed_at'] = now();
        }

        return $data;
    }
}
