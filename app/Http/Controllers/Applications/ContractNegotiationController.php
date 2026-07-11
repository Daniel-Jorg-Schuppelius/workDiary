<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractNegotiationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Models\Applications\{ApplicationContractNegotiation, ApplicationOpportunity, JobApplication};
use App\Models\User;
use App\Services\Applications\ContractNegotiationService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Vertragsverhandlungen (Feature 068, MVP-195–197): Aktionen laufen im
 * Kontext der Eltern-Akte (Ausschreibung oder Bewerbung); die Rechte
 * folgen dem Kontext (ApplicationContractNegotiationPolicy).
 */
class ContractNegotiationController extends Controller {
    public function __construct(private readonly ContractNegotiationService $negotiations) {}

    public function storeForTender(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('decide', $opportunity);

        return $this->open($request, $opportunity);
    }

    public function storeForApplication(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('decide', $application);

        return $this->open($request, $application);
    }

    public function addVersion(Request $request, ApplicationContractNegotiation $negotiation): RedirectResponse {
        Gate::authorize('update', $negotiation);
        $data = $request->validate([
            'kind' => ['required', 'in:draft,counter,final'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->negotiations->addVersion(
                $negotiation,
                $data['kind'],
                $data['summary'] ?? null,
                array_filter($data['conditions'] ?? [], fn($value): bool => trim((string) $value) !== ''),
                $this->actor(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Vertragsversion abgelegt.'));
    }

    public function addReviewItem(Request $request, ApplicationContractNegotiation $negotiation): RedirectResponse {
        Gate::authorize('update', $negotiation);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:500'],
            'severity' => ['required', 'in:info,important,blocker'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->negotiations->addReviewItem($negotiation, $data['label'], $data['severity'], $data['note'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Review-Punkt erfasst.'));
    }

    public function resolveReviewItem(Request $request, ApplicationContractNegotiation $negotiation, int $item): RedirectResponse {
        Gate::authorize('update', $negotiation);
        $data = $request->validate([
            'resolution' => ['required', 'in:resolved,accepted'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->negotiations->resolveReviewItem($negotiation, $item, $data['resolution'], $data['note'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Review-Punkt entschieden.'));
    }

    public function approve(Request $request, ApplicationContractNegotiation $negotiation): RedirectResponse {
        Gate::authorize('decide', $negotiation);

        try {
            $result = $this->negotiations->approve($negotiation, $this->actor(), $request->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $result === 'approved_all'
            ? __('Alle Freigabestufen erteilt.')
            : __('Freigabestufe erteilt — weitere Stufe offen.'));
    }

    public function conclude(Request $request, ApplicationContractNegotiation $negotiation): RedirectResponse {
        Gate::authorize('decide', $negotiation);
        $data = $request->validate([
            'decision' => ['required', 'in:concluded,declined'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->negotiations->conclude($negotiation, $data['decision'], $data['note'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Verhandlung abgeschlossen.'));
    }

    private function open(Request $request, ApplicationOpportunity|JobApplication $parent): RedirectResponse {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_on' => ['nullable', 'date'],
        ]);

        try {
            $this->negotiations->open($parent, $data['title'], $data['due_on'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Vertragsverhandlung eröffnet.'));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
