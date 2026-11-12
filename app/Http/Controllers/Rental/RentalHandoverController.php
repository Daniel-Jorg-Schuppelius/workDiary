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

use App\Enums\Rental\{RentalCondition, RentalConditionItemState, RentalReturnFollowUp};
use App\Exceptions\AssetNotUsableException;
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Rental\{RentalCase, RentalProfile, RentalReservation};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Attachments\FileAttacher;
use App\Services\Fields\FieldDocument;
use App\Services\Rental\RentalCaseService;
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\Carbon;
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
            return back()->withErrors(['asset_id' => ErrorText::for($e)]);
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
            return back()->withErrors(['asset_id' => ErrorText::for($e)]);
        }

        foreach ((array) $request->file('photos', []) as $photo) {
            app(FileAttacher::class)->store($report, $photo, $actor->id);
        }

        return back()->with('status', __('Rücknahme protokolliert.'));
    }

    /** Reinigung erledigt (MVP-1604): beendet das Reinigungsfenster, das Gerät ist wieder vergebbar. */
    public function cleaningDone(Request $request, RentalReservation $reservation): RedirectResponse {
        $rental = $reservation->rentalCase;
        $rental !== null ? Gate::authorize('handover', $rental) : Gate::authorize('create', RentalCase::class);

        try {
            $this->service->completeCleaning($reservation, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['reservation' => ErrorText::for($e)]);
        }

        return back()->with('status', __('rental.cleaning.done'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $forReturn): array {
        if ($request->filled('asset_id')) {
            $request->merge(['asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id'))]);
        }

        // Leer gelassene Zeilen des Formulars sind keine Positionen.
        foreach (['condition_items', 'accessory_items', 'consumables'] as $rows) {
            if (is_array($request->input($rows))) {
                $request->merge([$rows => array_values(array_filter(
                    $request->input($rows),
                    static fn (mixed $row): bool => is_array($row) && trim((string) ($row['label'] ?? '')) !== '',
                ))]);
            }
        }

        $rules = [
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'reported_at' => ['nullable', 'date', new \App\Rules\TimestampRange()],
            'condition' => ['required', Rule::enum(RentalCondition::class)],
            'checklist' => ['sometimes', 'array'],
            'checklist.*' => ['string', 'max:255'],
            'meter_value' => ['nullable', 'numeric', 'min:0'],
            'operating_hours' => ['nullable', 'numeric', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'signature_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
            'condition_items' => ['sometimes', 'array'],
            'condition_items.*.label' => ['required_with:condition_items', 'string', 'max:255'],
            'condition_items.*.state' => ['nullable', Rule::enum(RentalConditionItemState::class)],
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
                'consumables.*.label' => ['required', 'string', 'max:255'],
                'consumables.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'consumables.*.unit' => ['nullable', 'string', 'max:20'],
                'follow_up' => ['required', Rule::enum(RentalReturnFollowUp::class)],
                'follow_up_note' => ['nullable', 'string', 'max:4000', 'required_if:follow_up,repair,block,claim'],
            ];
        }

        $data = $request->validate($rules);

        // Checkliste (MVP-1604): Prüfpunkte kommen aus dem Verleihprofil, das Formular nennt die erledigten.
        $labels = RentalProfile::query()->where('asset_id', $data['asset_id'])->value('checklist_labels');
        $done = array_values((array) ($data['checklist'] ?? []));
        unset($data['checklist']);
        if (is_array($labels) && $labels !== []) {
            $data['checklist'] = FieldDocument::checklist($labels, $done);
        }

        if (! empty($data['reported_at'])) {
            $data['reported_at'] = Carbon::instance(Tz::parse((string) $data['reported_at']));
        }
        if ($data['signature_name'] ?? null) {
            $data['signed_at'] = now();
        }

        return $data;
    }
}
