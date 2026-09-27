<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicInvestmentProposalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\Investments\InvestmentOrigin;
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\Investments\InvestmentProposalService;
use App\Services\Licensing\ModuleStatusResolver;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/** Öffentliches Formular für Investitionsvorschläge (MVP-936); ohne gültigen, freigeschalteten Link 404. */
class PublicInvestmentProposalController extends Controller {
    public function __construct(
        private readonly InvestmentProposalService $proposals,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function show(string $token): View {
        $organization = $this->organization($token);

        return view('public.investment-proposal', ['orgName' => $organization->name, 'token' => $token, 'sent' => session('investment_proposal_sent', false)]);
    }

    public function store(Request $request, string $token): RedirectResponse {
        $organization = $this->organization($token);
        $this->proposals->propose($organization, InvestmentProposalController::validated($request, true), InvestmentOrigin::Public);

        return redirect()->route('investment-proposal.public', $token)->with('investment_proposal_sent', true);
    }

    private function organization(string $token): Organization {
        $organization = $this->proposals->resolve($token);
        abort_unless($organization instanceof Organization && $this->modules->isActiveFor($organization, 'module.investments'), 404);

        return $organization;
    }
}
