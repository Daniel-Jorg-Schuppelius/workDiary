<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqPricingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Services\Gaeb\{BoqPricingService, EfbPriceSheetPdfRenderer};
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * LV bepreisen und EFB-Preisblätter ausgeben (MVP-1056). Bepreisen dürfen,
 * wer Projekte bearbeiten darf; die Preisblätter sieht, wer das LV sieht.
 */
class BoqPricingController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly BoqPricingService $pricing) {}

    public function edit(BillOfQuantity $billOfQuantity): View {
        Gate::authorize(P::ProjectUpdate->value);
        $this->assertOwn($billOfQuantity);

        return view('bill-of-quantities.pricing', [
            'bill' => $billOfQuantity,
            'items' => $billOfQuantity->items()->orderBy('position')->orderBy('id')->get()->filter(fn (BoqItem $item): bool => $item->type->isPriceable())->values(),
            'kinds' => $this->pricing->componentKinds($billOfQuantity),
            'editable' => $this->pricing->isEditable($billOfQuantity),
        ]);
    }

    public function update(Request $request, BillOfQuantity $billOfQuantity): RedirectResponse {
        Gate::authorize(P::ProjectUpdate->value);
        $this->assertOwn($billOfQuantity);
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'rows.*.components' => ['nullable', 'array', 'max:6'],
            'rows.*.components.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'rows.*.not_offered' => ['nullable', 'boolean'],
        ]);
        $rows = [];
        foreach ($data['rows'] as $key => $row) {
            $id = Sqid::decode(BoqItem::class, (string) $key);
            if ($id !== null) {
                $rows[$id] = $row;
            }
        }

        try {
            $changed = $this->pricing->price($billOfQuantity, $rows);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('bill-of-quantities.pricing', $billOfQuantity)
            ->with('success', trans_choice('gaeb.pricing.saved', $changed, ['count' => $changed]));
    }

    public function efb(BillOfQuantity $billOfQuantity, string $form, EfbPriceSheetPdfRenderer $renderer): Response {
        Gate::authorize(P::ProjectViewAny->value);
        $this->assertOwn($billOfQuantity);
        abort_unless(in_array($form, EfbPriceSheetPdfRenderer::FORMS, true), 404);

        return response($renderer->render($billOfQuantity, $form), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($billOfQuantity, $form) . '"',
        ]);
    }

    private function assertOwn(BillOfQuantity $bill): void {
        abort_unless($bill->organization_id === $this->currentOrganization()->id, 404);
    }
}
