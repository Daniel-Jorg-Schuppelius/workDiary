<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicSupplierQuestionnaireController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Supplier\SupplierQuestionnaireRequest;
use App\Services\Supplier\SupplierQuestionnaireService;
use App\Support\OrganizationContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/** Öffentliches Ausfüllen der Selbstauskunft (MVP-937); ungültiger oder abgelaufener Link → 404. */
class PublicSupplierQuestionnaireController extends Controller {
    public function __construct(private readonly SupplierQuestionnaireService $service) {}

    public function show(string $token): View {
        if (hash_equals((string) session('supplier_questionnaire_sent', ''), $token)) {
            return view('public.supplier-questionnaire', ['sent' => true, 'request' => null, 'orgName' => (string) session('supplier_questionnaire_org'), 'token' => $token]);
        }
        $request = $this->request($token);

        return view('public.supplier-questionnaire', ['sent' => false, 'request' => $request, 'orgName' => $this->organization($request)->name, 'token' => $token]);
    }

    public function store(Request $http, string $token): RedirectResponse {
        $request = $this->request($token);
        $organization = $this->organization($request);
        OrganizationContext::run($organization, fn () => $this->service->submit($request, (array) $http->input('values', [])));

        return redirect()->route('supplier-questionnaire.public', $token)->with('supplier_questionnaire_sent', $token)->with('supplier_questionnaire_org', $organization->name);
    }

    private function request(string $token): SupplierQuestionnaireRequest {
        return $this->service->resolve($token) ?? abort(404);
    }

    private function organization(SupplierQuestionnaireRequest $request): Organization {
        return Organization::query()->withoutGlobalScopes()->findOrFail($request->organization_id);
    }
}
