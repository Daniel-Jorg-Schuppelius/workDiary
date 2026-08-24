<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateCardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Enums\Rental\{RentalChargeKind, RentalRateCardStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Rental\{RentalRateCard, RentalRateItem};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Versionierte Verleih-Preislisten (D10, MVP-262): Neue Versionen lösen die
 * aktive ab; abgelöste Versionen bleiben lesbar, weil Verleihakten sie als
 * Snapshot referenzieren. Alte Fälle werden nie umbewertet.
 */
class RentalRateCardController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', RentalRateCard::class);

        return view('rental.rates.index', [
            'cards' => RentalRateCard::query()
                ->with('items')
                ->orderBy('name')
                ->orderByDesc('version')
                ->paginate(25),
            'kinds' => RentalChargeKind::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', RentalRateCard::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Neue Version: bestehende aktive Version gleichen Namens abloesen.
        $previous = RentalRateCard::query()
            ->where('name', $data['name'])
            ->orderByDesc('version')
            ->first();

        $card = RentalRateCard::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'name' => $data['name'],
            'version' => $previous !== null ? $previous->version + 1 : 1,
            'status' => RentalRateCardStatus::Draft->value,
            'valid_from' => $data['valid_from'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        // Positionen der Vorversion übernehmen (Bearbeitung im Entwurf).
        if ($previous !== null) {
            foreach ($previous->items as $item) {
                RentalRateItem::query()->create([
                    'organization_id' => $card->organization_id,
                    'rental_rate_card_id' => $card->id,
                    'kind' => $item->kind->value,
                    'label' => $item->label,
                    'group_code' => $item->group_code,
                    'amount' => $item->amount,
                    'unit' => $item->unit,
                    'min_duration_days' => $item->min_duration_days,
                    'note' => $item->note,
                ]);
            }
        }

        return back()->with('status', __('Preislisten-Version :version angelegt.', ['version' => $card->version]));
    }

    /** Entwurf aktivieren — löst die bisher aktive Version ab (D10). */
    public function activate(RentalRateCard $rateCard): RedirectResponse {
        Gate::authorize('update', $rateCard);

        if ($rateCard->status !== RentalRateCardStatus::Draft) {
            return back()->withErrors(['status' => __('Nur Entwürfe können aktiviert werden.')]);
        }

        RentalRateCard::query()
            ->where('name', $rateCard->name)
            ->where('status', RentalRateCardStatus::Active->value)
            ->whereKeyNot($rateCard->id)
            ->get()
            ->each(fn (RentalRateCard $old) => $old->forceFill([
                'status' => RentalRateCardStatus::Retired->value,
                'valid_to' => now()->toDateString(),
            ])->save());

        $rateCard->forceFill([
            'status' => RentalRateCardStatus::Active->value,
            'valid_from' => $rateCard->valid_from ?? now()->toDateString(),
        ])->save();

        return back()->with('status', __('Preisliste aktiviert.'));
    }

    public function storeItem(Request $request, RentalRateCard $rateCard): RedirectResponse {
        Gate::authorize('update', $rateCard);

        if ($rateCard->status !== RentalRateCardStatus::Draft) {
            return back()->withErrors(['status' => __('Aktive oder abgelöste Versionen sind unveränderlich — neue Version anlegen.')]);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::enum(RentalChargeKind::class)],
            'label' => ['required', 'string', 'max:255'],
            'group_code' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'min_duration_days' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        RentalRateItem::query()->create(array_merge($data, [
            'organization_id' => $rateCard->organization_id,
            'rental_rate_card_id' => $rateCard->id,
        ]));

        return back()->with('status', __('Kondition ergänzt.'));
    }

    public function destroyItem(RentalRateCard $rateCard, RentalRateItem $item): RedirectResponse {
        Gate::authorize('update', $rateCard);

        if ($rateCard->status !== RentalRateCardStatus::Draft || (int) $item->rental_rate_card_id !== (int) $rateCard->id) {
            return back()->withErrors(['status' => __('Nur Entwurfs-Konditionen können entfernt werden.')]);
        }

        $item->delete();

        return back()->with('status', __('Kondition entfernt.'));
    }
}
