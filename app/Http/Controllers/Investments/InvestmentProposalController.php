<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProposalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\Investments\InvestmentOrigin;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Investments\InvestmentCase;
use App\Services\Investments\InvestmentProposalService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Investitionsvorschläge (MVP-936): Vorschlag intern und Verwaltung des öffentlichen Links. */
class InvestmentProposalController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly InvestmentProposalService $proposals) {}

    public function create(): View {
        Gate::authorize(P::InvestmentPropose->value);

        return view('investments._proposal_dialog');
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::InvestmentPropose->value);
        $this->proposals->propose($this->currentOrganization(), self::validated($request), InvestmentOrigin::Staff, $this->authUser());

        return back()->with('success', __('investment.proposal.flash.submitted'));
    }

    public function link(Request $request): View {
        Gate::authorize(P::InvestmentManage->value);

        return view('investments._proposal_link_dialog', [
            'status' => $this->proposals->status($this->currentOrganization()),
            'token' => $request->session()->get('investment_proposal_token'),
        ]);
    }

    public function rotate(): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $token = $this->proposals->issue($this->currentOrganization());

        return redirect()->route('investments.proposals.link')->with('investment_proposal_token', $token)->with('success', __('investment.proposal.flash.issued'));
    }

    public function toggle(Request $request): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $this->proposals->setEnabled($this->currentOrganization(), $request->boolean('enabled'));

        return redirect()->route('investments.proposals.link')->with('success', __('investment.proposal.flash.saved'));
    }

    public function revoke(): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $this->proposals->revoke($this->currentOrganization());

        return redirect()->route('investments.proposals.link')->with('success', __('investment.proposal.flash.revoked'));
    }

    /** @return array{title: string, reason: string, category: string, urgency: string, estimated_amount?: ?string, submitter_name?: ?string, submitter_email?: ?string} */
    public static function validated(Request $request, bool $public = false): array {
        /** @var array{title: string, reason: string, category: string, urgency: string, estimated_amount?: ?string, submitter_name?: ?string, submitter_email?: ?string} */
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'reason' => ['required', 'string', 'min:10', 'max:4000'],
            'category' => ['required', Rule::in(InvestmentCase::CATEGORIES)],
            'urgency' => ['required', 'in:low,medium,high'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'submitter_name' => [$public ? 'required' : 'nullable', 'string', 'max:200'],
            'submitter_email' => [$public ? 'required' : 'nullable', 'email:rfc', 'max:255'],
        ]);
    }
}
