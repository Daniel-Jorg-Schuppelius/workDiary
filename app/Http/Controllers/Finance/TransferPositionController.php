<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferPositionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\TransferStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Services\Ai\Exceptions\{AiException, AiProviderCallException, AiUnavailableException};
use App\Services\Ai\Suggestions\ItemTextSuggestionService;
use App\Services\Invoicing\TextCorrectionDiff;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Prüfen und Nachbessern der eingefrorenen Übergabe-Positionen (MVP-487/488):
 * Bezeichnung und Leistungstext darf ändern, wer die Übergabe bestätigen darf;
 * Menge und Einzelpreis nur mit `finance.config` — sie bestimmen den
 * Rechnungsbetrag. Nach dem Übertragen ist nichts mehr änderbar.
 */
class TransferPositionController extends Controller {
    public function __construct(private readonly ItemTextSuggestionService $suggestions) {}

    public function update(Request $request, BillingTransfer $transfer, BillingTransferPosition $position): RedirectResponse {
        $this->authorizePosition($transfer, $position);

        $mayPrice = Gate::allows(Permission::FinanceConfig->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:4000'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $oldDescription = (string) $position->description;

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ];

        if ($mayPrice) {
            $quantity = isset($data['quantity']) ? round((float) $data['quantity'], 3) : $position->quantityFloat();
            $unitPrice = isset($data['unit_price']) ? round((float) $data['unit_price'], 4) : $position->unitPriceFloat();
            $attributes['quantity'] = $quantity;
            $attributes['unit_price'] = $unitPrice;
            $attributes['amount'] = round($quantity * $unitPrice, 2);
        }

        $position->update($attributes);

        // Wörterbuch-Kandidaten aus der manuellen Korrektur — Aufnahme NUR
        // über den bestätigten „Merken?"-Dialog, nie still.
        $pairs = TextCorrectionDiff::candidates($oldDescription, (string) ($attributes['description'] ?? ''));
        if ($pairs !== []) {
            session()->flash('text_correction_learn', ['pairs' => $pairs]);
        }

        // Nachvollziehbarkeit über die bestehende Ereignis-Hash-Kette.
        $transfer->events()->create([
            'organization_id' => $transfer->organization_id,
            'event' => 'position_edited',
            'actor_user_id' => Auth::id(),
            'payload' => [
                'position_id' => (int) $position->id,
                'position' => (int) $position->position,
                'price_changed' => $mayPrice,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', __('finance.flash.position_updated'));
    }

    /**
     * Position aus der Übergabe nehmen (MVP-492). Die zugrunde liegende Zeit
     * bleibt dem Nachweis zugeordnet und damit verbraucht — sie verschwindet
     * nur von der Rechnung. Für ein echtes Freigeben ist der Nachweis zu
     * verwerfen und neu anzulegen.
     */
    public function destroy(BillingTransfer $transfer, BillingTransferPosition $position): RedirectResponse {
        $this->authorizePosition($transfer, $position);
        abort_unless(Gate::allows(Permission::FinanceConfig->value), 403);

        $number = (int) $position->position;
        $position->delete();
        $this->renumber($transfer);

        $transfer->events()->create([
            'organization_id' => $transfer->organization_id,
            'event' => 'position_removed',
            'actor_user_id' => Auth::id(),
            'payload' => ['position' => $number],
            'created_at' => now(),
        ]);

        return back()->with('success', __('finance.flash.position_removed'));
    }

    /** Position eine Stelle nach oben oder unten (MVP-492). */
    public function move(Request $request, BillingTransfer $transfer, BillingTransferPosition $position): RedirectResponse {
        $this->authorizePosition($transfer, $position);

        $direction = (string) $request->input('direction');
        abort_unless(in_array($direction, ['up', 'down'], true), 422);

        $neighbour = $transfer->positions()
            ->where('position', $direction === 'up' ? '<' : '>', $position->position)
            ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbour !== null) {
            $own = (int) $position->position;
            $position->update(['position' => (int) $neighbour->position]);
            $neighbour->update(['position' => $own]);
        }

        return back();
    }

    /**
     * Mehrere Positionen zu einer zusammenfassen (MVP-492): Mengen und Beträge
     * addieren, Texte verketten, Quellen vereinigen. Der Einzelpreis ergibt
     * sich aus Betrag / Menge — sonst liefe die Summe am Betrag vorbei.
     */
    public function merge(Request $request, BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('confirm', $transfer);
        abort_unless(self::isOpenForEditing($transfer), 403);
        abort_unless(Gate::allows(Permission::FinanceConfig->value), 403);

        $data = $request->validate([
            'positions' => ['required', 'array', 'min:2'],
            'positions.*' => ['string'],
        ]);

        // Sqids aus dem Formular (W3.3); die Bindung an den Transfer
        // (positions()-Relation) bleibt die eigentliche Schutzlinie.
        $requested = array_filter(array_map(
            static fn (string $v): ?int => \App\Support\Sqid::decodeOrNumeric(\App\Models\Finance\BillingTransferPosition::class, $v),
            $data['positions'],
        ));

        $positions = $transfer->positions()->whereIn('id', $requested)->orderBy('position')->get();
        if ($positions->count() < 2) {
            return back()->withErrors(['positions' => __('finance.error.merge_needs_two')]);
        }

        /** @var BillingTransferPosition $target */
        $target = $positions->first();
        $quantity = round((float) $positions->sum(fn(BillingTransferPosition $p): float => $p->quantityFloat()), 3);
        $amount = round((float) $positions->sum(fn(BillingTransferPosition $p): float => $p->amountFloat()), 2);

        $sourceIds = $positions->flatMap(fn(BillingTransferPosition $p): array => is_array($p->source_ids) ? $p->source_ids : [])
            ->unique()->values()->all();
        $descriptions = $positions
            ->map(fn(BillingTransferPosition $p): string => trim((string) $p->description))
            ->filter()->unique()->implode("\n");
        $from = $positions->min('service_from');
        $to = $positions->max('service_to');

        $target->update([
            'quantity' => $quantity,
            'amount' => $amount,
            'unit_price' => $quantity > 0 ? round($amount / $quantity, 4) : $target->unitPriceFloat(),
            'description' => $descriptions !== '' ? $descriptions : null,
            'source_ids' => $sourceIds,
            'service_from' => $from,
            'service_to' => $to,
        ]);

        $transfer->positions()->whereIn('id', $positions->skip(1)->pluck('id'))->delete();
        $this->renumber($transfer);

        $transfer->events()->create([
            'organization_id' => $transfer->organization_id,
            'event' => 'positions_merged',
            'actor_user_id' => Auth::id(),
            'payload' => ['count' => $positions->count(), 'into' => (int) $target->id],
            'created_at' => now(),
        ]);

        return back()->with('success', __('finance.flash.positions_merged'));
    }

    /** Lückenlose Nummerierung nach Entfernen/Zusammenfassen. */
    private function renumber(BillingTransfer $transfer): void {
        $index = 0;
        foreach ($transfer->positions()->orderBy('position')->get() as $position) {
            $position->update(['position' => ++$index]);
        }
    }

    /** KI-Textvorschlag für eine Position (Feature 084 / MVP-488). */
    public function suggest(BillingTransfer $transfer, BillingTransferPosition $position): RedirectResponse {
        $this->authorizePosition($transfer, $position);
        abort_unless(Gate::allows(Permission::AiUse->value), 403);

        try {
            $this->suggestions->suggestForTransferPosition($transfer, $position, Auth::user());
        } catch (AiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('ai.flash.suggestion_created'));
    }

    /**
     * Sammelaktion: je Position ein Vorschlag (synchron). Ein Fehler an einer
     * einzelnen Position hält den Lauf nicht auf — ein Provider- oder
     * Routing-Fehler schon: der trifft jede weitere Position genauso, und das
     * HTTP-Fundament wiederholt jeden Versuch mehrfach. Weiterlaufen hieße,
     * denselben aussichtslosen Aufruf n-mal zu bezahlen.
     */
    public function suggestAll(BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('confirm', $transfer);
        abort_unless(self::isOpenForEditing($transfer), 403);
        abort_unless(Gate::allows(Permission::AiUse->value), 403);

        $count = 0;
        $lastError = null;
        $aborted = false;

        foreach ($transfer->positions as $position) {
            try {
                $this->suggestions->suggestForTransferPosition($transfer, $position, Auth::user());
                $count++;
            } catch (AiProviderCallException|AiUnavailableException $e) {
                $lastError = $e->getMessage();
                $aborted = true;
                break;
            } catch (AiException $e) {
                $lastError = $e->getMessage();
            }
        }

        if ($lastError !== null && ($count === 0 || $aborted)) {
            return back()->with('error', $count > 0
                ? __('ai.flash.suggestions_aborted', ['count' => $count, 'error' => $lastError])
                : $lastError);
        }

        return back()->with('success', __('ai.flash.suggestions_queued', ['count' => $count]));
    }

    private function authorizePosition(BillingTransfer $transfer, BillingTransferPosition $position): void {
        Gate::authorize('confirm', $transfer);
        abort_unless((int) $position->billing_transfer_id === (int) $transfer->id, 404);
        abort_unless(self::isOpenForEditing($transfer), 403);
    }

    /**
     * Bearbeitbar ist eine Position zwischen Bestätigen und Übertragen. Für
     * einen bereits übergebenen Nachweis führt der Weg über „Korrektur
     * vorbereiten" (MVP-489) — der setzt ihn zurück auf bestätigt.
     */
    public static function isOpenForEditing(BillingTransfer $transfer): bool {
        return $transfer->status === TransferStatus::Confirmed;
    }
}
