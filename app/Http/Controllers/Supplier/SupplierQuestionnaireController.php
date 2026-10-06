<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaireController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Supplier;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Supplier\{Supplier, SupplierQuestionnaire, SupplierQuestionnaireRequest};
use App\Services\Supplier\SupplierQuestionnaireService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Lieferanten-Selbstauskunft (MVP-937): Fragebögen, Anfragen, Prüfung. */
class SupplierQuestionnaireController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly SupplierQuestionnaireService $service) {}

    public function index(): View {
        Gate::authorize(P::SupplierViewAny->value);

        return view('suppliers.questionnaires.index', [
            'questionnaires' => SupplierQuestionnaire::query()->withCount('requests')->orderBy('name')->get(),
            'requests' => SupplierQuestionnaireRequest::query()->with(['supplier', 'questionnaire'])->orderByDesc('id')->paginate(25)->withQueryString(),
            'canManage' => Gate::allows(P::SupplierUpdate->value),
        ]);
    }

    public function create(): View {
        Gate::authorize(P::SupplierUpdate->value);

        return view('suppliers.questionnaires._form_dialog', ['questionnaire' => null]);
    }

    public function edit(SupplierQuestionnaire $questionnaire): View {
        Gate::authorize(P::SupplierUpdate->value);

        return view('suppliers.questionnaires._form_dialog', ['questionnaire' => $questionnaire]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::SupplierUpdate->value);
        $this->service->save($this->currentOrganization(), null, $this->validated($request), $this->authUser());

        return redirect()->toList('supplier-questionnaires.index')->with('success', __('supplier_questionnaire.flash.saved'));
    }

    public function update(Request $request, SupplierQuestionnaire $questionnaire): RedirectResponse {
        Gate::authorize(P::SupplierUpdate->value);
        $this->service->save($this->currentOrganization(), $questionnaire, $this->validated($request), $this->authUser());

        return redirect()->toList('supplier-questionnaires.index')->with('success', __('supplier_questionnaire.flash.saved'));
    }

    public function sendForm(Supplier $supplier): View {
        Gate::authorize(P::SupplierUpdate->value);

        return view('suppliers.questionnaires._send_dialog', [
            'supplier' => $supplier,
            'questionnaires' => SupplierQuestionnaire::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function send(Request $request, Supplier $supplier): RedirectResponse {
        Gate::authorize(P::SupplierUpdate->value);
        $request->merge(['questionnaire_id' => Sqid::decodeOrNumeric(SupplierQuestionnaire::class, $request->string('questionnaire_id')->toString())]);
        $data = $request->validate([
            'questionnaire_id' => ['required', 'integer'],
            'recipient_email' => ['required', 'email:rfc', 'max:255'],
        ]);
        $questionnaire = SupplierQuestionnaire::query()->where('is_active', true)->findOrFail((int) $data['questionnaire_id']);
        $this->service->send($questionnaire, $supplier, $data['recipient_email'], $this->authUser());

        return back()->with('success', __('supplier_questionnaire.flash.sent', ['email' => $data['recipient_email']]));
    }

    public function show(SupplierQuestionnaireRequest $questionnaireRequest): View {
        Gate::authorize(P::SupplierView->value);

        return view('suppliers.questionnaires._request_dialog', [
            'request' => $questionnaireRequest->load(['supplier', 'questionnaire']),
            'lines' => $this->service->answerLines($questionnaireRequest),
            'canManage' => Gate::allows(P::SupplierUpdate->value),
        ]);
    }

    public function review(Request $request, SupplierQuestionnaireRequest $questionnaireRequest): RedirectResponse {
        Gate::authorize(P::SupplierUpdate->value);
        $data = $request->validate(['decision' => ['required', 'in:accept,reject'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->service->review($questionnaireRequest, $data['decision'] === 'accept', $data['note'] ?? null, $this->authUser());

        return back()->with('success', __('supplier_questionnaire.flash.reviewed'));
    }

    /** @return array{name: string, description?: ?string, validity_months: int, is_active?: bool, fields: array<int|string, mixed>} */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'validity_months' => ['required', 'integer', 'min:1', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['required', 'array'],
            'fields.*' => ['array'],
            'fields.*.label' => ['nullable', 'string', 'max:160'],
            'fields.*.type' => ['nullable', 'string', 'max:32'],
            'fields.*.required' => ['nullable'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            'fields.*.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.min' => ['nullable', 'numeric'],
            'fields.*.max' => ['nullable', 'numeric'],
            'fields.*.visible_if' => ['nullable', 'array'],
            'fields.*.visible_if.field' => ['nullable', 'string', 'max:160'],
            'fields.*.visible_if.op' => ['nullable', 'string', 'max:16'],
            'fields.*.visible_if.value' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['validity_months'] = (int) $data['validity_months'];

        return $data;
    }
}
