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
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\ItemTextSuggestionService;
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

    /** Sammelaktion: je Position ein Vorschlag (synchron, Fehler je Position stoppen nicht). */
    public function suggestAll(BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('confirm', $transfer);
        abort_unless($transfer->status === TransferStatus::Confirmed, 403);
        abort_unless(Gate::allows(Permission::AiUse->value), 403);

        $count = 0;
        $lastError = null;

        foreach ($transfer->positions as $position) {
            try {
                $this->suggestions->suggestForTransferPosition($transfer, $position, Auth::user());
                $count++;
            } catch (AiException $e) {
                $lastError = $e->getMessage();
            }
        }

        if ($count === 0 && $lastError !== null) {
            return back()->with('error', $lastError);
        }

        return back()->with('success', __('ai.flash.suggestions_queued', ['count' => $count]));
    }

    private function authorizePosition(BillingTransfer $transfer, BillingTransferPosition $position): void {
        Gate::authorize('confirm', $transfer);
        abort_unless((int) $position->billing_transfer_id === (int) $transfer->id, 404);
        // Nur zwischen Bestätigen und Übertragen: davor gibt es keine
        // eingefrorenen Positionen, danach hängt der Beleg beim Zielsystem.
        abort_unless($transfer->status === TransferStatus::Confirmed, 403);
    }
}
