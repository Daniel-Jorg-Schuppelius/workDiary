<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRecourseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Claims;

use App\Enums\Claims\ClaimRecourseStatus;
use App\Http\Controllers\Controller;
use App\Models\Claims\{ClaimCase, ClaimSupplierRecourse};
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Lieferanten-/Herstellerregress (Feature 072, MVP-253): eigener Anspruch
 * gegenüber dem Vorlieferanten mit Antwortfrist und Kostenrückfluss.
 * Rügepflicht (§ 377 HGB) gilt in Gegenrichtung → Standard-Antwortfrist.
 */
class ClaimRecourseController extends Controller {
    public function store(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('recourse', $claim);

        $fieldModels = [
            'supplier_id' => \App\Models\Supplier::class,
            'purchase_order_id' => \App\Models\PurchaseOrder::class,
            'incoming_einvoice_id' => \App\Models\IncomingEInvoice::class,
            'article_id' => \App\Models\Article::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'purchase_order_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('purchase_orders')],
            'incoming_einvoice_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('incoming_einvoices')],
            'article_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('articles')],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'warranty_terms' => ['nullable', 'string', 'max:4000'],
            'amount_claimed' => ['nullable', 'numeric', 'min:0'],
        ]);

        $claim->supplierRecourses()->create(array_merge($data, [
            'organization_id' => $claim->organization_id,
            'status' => ClaimRecourseStatus::Draft->value,
            'created_by' => ($request->user() ?? abort(401))->id,
        ]));

        return back()->with('status', __('Regressfall angelegt.'));
    }

    public function update(Request $request, ClaimSupplierRecourse $recourse): RedirectResponse {
        Gate::authorize('recourse', $recourse->claimCase);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ClaimRecourseStatus::class)],
            'amount_recovered' => ['nullable', 'numeric', 'min:0'],
            'outcome_note' => ['nullable', 'string', 'max:4000'],
            'response_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $status = ClaimRecourseStatus::from($data['status']);
        $fill = [
            'status' => $status->value,
            'amount_recovered' => $data['amount_recovered'] ?? $recourse->amount_recovered,
            'outcome_note' => $data['outcome_note'] ?? $recourse->outcome_note,
        ];
        if ($status === ClaimRecourseStatus::Submitted && $recourse->submitted_at === null) {
            $fill['submitted_at'] = now();
            // Antwortfrist: Standard 14 Tage, übersteuerbar je Fall.
            $fill['response_due_at'] = now()->addDays((int) ($data['response_due_days'] ?? config('claims.recourse_response_days', 14)));
        }
        if (in_array($status, [ClaimRecourseStatus::Accepted, ClaimRecourseStatus::PartiallyAccepted, ClaimRecourseStatus::Rejected], true) && $recourse->responded_at === null) {
            $fill['responded_at'] = now();
        }
        $recourse->forceFill($fill)->save();

        return back()->with('status', __('Regressfall aktualisiert.'));
    }
}
