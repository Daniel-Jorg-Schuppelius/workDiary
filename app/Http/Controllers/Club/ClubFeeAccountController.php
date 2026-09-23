<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeAccountController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubFeeExemptionKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveFeeAccountRequest, SaveFeeAssignmentRequest, SaveFeeExemptionRequest};
use App\Models\Club\{ClubFeeAccount, ClubFeeAssignment, ClubFeeExemption, ClubFeeTariff, ClubMember};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Club\{ClubFeeCalculator, ClubFeeService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Beitragskonten, Zuordnungen, Befreiungen und Vorschau (Feature 159, MVP-849).
 * Kein Beitragslauf hier — die Vorschau zeigt nur die Berechnung eines Monats.
 */
class ClubFeeAccountController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubFeeService $fees,
        private readonly ClubFeeCalculator $calculator,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $q = trim((string) $request->query('q', ''));
        $today = CarbonImmutable::today();

        $accounts = ClubFeeAccount::query()
            ->with(['customer:id,name,number'])
            ->withCount(['assignments as active_assignments_count' => fn($query) => $query->overlapping($today, $today)])
            ->when($q !== '', fn($query) => $query->where(fn($inner) => $inner->whereLikeEscaped('name', $q)->orWhereLikeEscaped('email', $q)))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();
        $review = ClubFeeAssignment::query()->whereNotNull('review_required_at')->overlapping($today, $today)->with(['member', 'tariff', 'account'])->get();

        return view('club.fees.accounts.index', [
            'accounts' => $accounts,
            'filters' => ['q' => $q],
            'review' => $review,
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function show(ClubFeeAccount $account): View {
        Gate::authorize('view', $account);
        $account->load(['customer', 'assignments.member', 'assignments.tariff']);
        $memberIds = $account->assignments->pluck('club_member_id')->unique();

        return view('club.fees.accounts.show', [
            'account' => $account,
            'exemptions' => ClubFeeExemption::query()->whereIn('club_member_id', $memberIds)->with(['member', 'createdBy:id,name'])->orderByDesc('starts_on')->get(),
            // Forderungen und offener Saldo (MVP-850).
            'claims' => \App\Models\Club\ClubFeeClaim::query()->where('club_fee_account_id', $account->id)->orderByDesc('sequence')->limit(50)->get(),
            'openAmount' => app(\App\Services\Club\ClubFeeRunService::class)->openAmountFor($account),
            // Zahlungen, Guthaben, Mandat, Portalzugang (MVP-851).
            'payments' => \App\Models\Club\ClubFeePayment::query()->where('club_fee_account_id', $account->id)->with(['claim:id,number', 'createdBy:id,name'])->orderByDesc('paid_on')->orderByDesc('id')->limit(50)->get(),
            'credit' => app(\App\Services\Club\ClubFeePaymentService::class)->creditBalance($account),
            'mandate' => app(\App\Services\Club\ClubFeePaymentService::class)->mandateFor($account),
            'portalUser' => $account->user_id !== null ? \App\Models\Platform\User::query()->find($account->user_id) : null,
            'today' => CarbonImmutable::today(),
            'canManage' => Gate::allows('update', $account),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.accounts._account_dialog', ['account' => null, 'customers' => $this->freeCustomers()]);
    }

    public function store(SaveFeeAccountRequest $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = Auth::user();
        $account = $this->fees->createAccount($this->currentOrganization(), $request->validated(), $actor);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.account_saved'));
    }

    public function edit(ClubFeeAccount $account): View {
        Gate::authorize('update', $account);

        return view('club.fees.accounts._account_dialog', ['account' => $account, 'customers' => collect()]);
    }

    public function update(SaveFeeAccountRequest $request, ClubFeeAccount $account): RedirectResponse {
        Gate::authorize('update', $account);
        $this->fees->updateAccount($account, $request->validated());

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.account_saved'));
    }

    // ── Zuordnungen ──────────────────────────────────────────────────────

    public function createAssignment(ClubFeeAccount $account): View {
        Gate::authorize('update', $account);

        return view('club.fees.accounts._assignment_dialog', ['account' => $account, 'assignment' => null] + $this->assignmentOptions());
    }

    public function storeAssignment(SaveFeeAssignmentRequest $request, ClubFeeAccount $account): RedirectResponse {
        Gate::authorize('update', $account);
        $data = $request->validated();
        $this->fees->assign($account, ClubMember::query()->findOrFail((int) $data['club_member_id']), ClubFeeTariff::query()->findOrFail((int) $data['club_fee_tariff_id']), $data);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.assignment_saved'));
    }

    public function editAssignment(ClubFeeAccount $account, ClubFeeAssignment $assignment): View {
        Gate::authorize('update', $account);
        abort_unless($assignment->club_fee_account_id === $account->id, 404);

        return view('club.fees.accounts._assignment_dialog', ['account' => $account, 'assignment' => $assignment->load(['member', 'tariff'])] + $this->assignmentOptions());
    }

    public function updateAssignment(SaveFeeAssignmentRequest $request, ClubFeeAccount $account, ClubFeeAssignment $assignment): RedirectResponse {
        Gate::authorize('update', $account);
        abort_unless($assignment->club_fee_account_id === $account->id, 404);
        $data = $request->validated();
        $this->fees->assign($account, ClubMember::query()->findOrFail((int) $data['club_member_id']), ClubFeeTariff::query()->findOrFail((int) $data['club_fee_tariff_id']), $data, $assignment);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.assignment_saved'));
    }

    public function endAssignment(Request $request, ClubFeeAccount $account, ClubFeeAssignment $assignment): RedirectResponse {
        Gate::authorize('update', $account);
        abort_unless($assignment->club_fee_account_id === $account->id, 404);
        $data = $request->validate(['valid_to' => ['required', 'date']]);
        $this->fees->endAssignment($assignment, CarbonImmutable::parse((string) $data['valid_to']));

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.assignment_ended'));
    }

    /** Altersbedingter Wechsel: Dialog mit Vorschlag, wirksam zum bestätigten Datum. */
    public function reviewDialog(ClubFeeAccount $account, ClubFeeAssignment $assignment): View {
        Gate::authorize('update', $account);
        abort_unless($assignment->club_fee_account_id === $account->id, 404);
        $assignment->load(['member', 'tariff']);

        return view('club.fees.accounts._review_dialog', [
            'account' => $account,
            'assignment' => $assignment,
            'suggested' => $this->fees->suggestTariff($assignment, CarbonImmutable::today()),
            'tariffs' => ClubFeeTariff::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function review(Request $request, ClubFeeAccount $account, ClubFeeAssignment $assignment): RedirectResponse {
        Gate::authorize('update', $account);
        abort_unless($assignment->club_fee_account_id === $account->id, 404);
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:change,keep'],
            'club_fee_tariff_id' => ['required_if:decision,change', 'nullable', 'string', 'max:64'],
            'effective_on' => ['required_if:decision,change', 'nullable', 'date'],
        ]);
        /** @var User $actor */
        $actor = Auth::user();
        if ($data['decision'] === 'keep') {
            $assignment->update(['review_required_at' => null, 'review_note' => null]);
            $assignment->audit('club.fee.reviewDismissed', ['actor_id' => $actor->id]);

            return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.review_kept'));
        }
        $tariffId = Sqid::decodeOrNumeric(ClubFeeTariff::class, (string) $data['club_fee_tariff_id']);
        $tariff = $tariffId !== null ? ClubFeeTariff::query()->find($tariffId) : null;
        abort_if($tariff === null, 404);
        $this->fees->changeTariff($assignment, $tariff, CarbonImmutable::parse((string) $data['effective_on']), $actor);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.tariff_changed', ['tariff' => $tariff->name]));
    }

    // ── Befreiungen ──────────────────────────────────────────────────────

    public function createExemption(ClubFeeAccount $account, ClubMember $member): View {
        Gate::authorize('update', $account);

        return view('club.fees.accounts._exemption_dialog', ['account' => $account, 'member' => $member, 'exemption' => null, 'kinds' => ClubFeeExemptionKind::cases()]);
    }

    public function storeExemption(SaveFeeExemptionRequest $request, ClubFeeAccount $account, ClubMember $member): RedirectResponse {
        Gate::authorize('update', $account);
        /** @var User $actor */
        $actor = Auth::user();
        $this->fees->saveExemption($member, $request->validated(), $actor);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.exemption_saved'));
    }

    public function editExemption(ClubFeeAccount $account, ClubFeeExemption $exemption): View {
        Gate::authorize('update', $account);

        return view('club.fees.accounts._exemption_dialog', ['account' => $account, 'member' => $exemption->member()->firstOrFail(), 'exemption' => $exemption, 'kinds' => ClubFeeExemptionKind::cases()]);
    }

    public function updateExemption(SaveFeeExemptionRequest $request, ClubFeeAccount $account, ClubFeeExemption $exemption): RedirectResponse {
        Gate::authorize('update', $account);
        /** @var User $actor */
        $actor = Auth::user();
        $this->fees->saveExemption($exemption->member()->firstOrFail(), $request->validated(), $actor, $exemption);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.exemption_saved'));
    }

    public function destroyExemption(ClubFeeAccount $account, ClubFeeExemption $exemption): RedirectResponse {
        Gate::authorize('update', $account);
        $this->fees->deleteExemption($exemption);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.exemption_deleted'));
    }

    // ── Vorschau ─────────────────────────────────────────────────────────

    /** Berechnung eines Abrechnungsmonats (ohne Forderung); Fehler in Zuordnungen werden sichtbar, nicht ausgelassen. */
    public function preview(Request $request): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $month = CarbonImmutable::createFromFormat('Y-m', (string) $request->query('month', CarbonImmutable::today()->format('Y-m'))) ?: CarbonImmutable::today();
        $month = $month->startOfMonth();
        $result = $this->calculator->calculateMonth($this->currentOrganization(), (int) $month->year, (int) $month->month);
        $accountIds = $result['positions']->pluck('accountId')->unique()->values();
        $memberIds = $result['positions']->pluck('memberId')->filter()->unique()->values();

        return view('club.fees.preview', [
            'month' => $month,
            'positions' => $result['positions'],
            'issues' => $result['issues'],
            'accounts' => ClubFeeAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id'),
            'members' => ClubMember::query()->whereIn('id', $memberIds)->get()->keyBy('id'),
            'total' => $result['positions']->isEmpty() ? null : \CommonToolkit\ValueObjects\Money::sum($result['positions']->map(fn($p) => $p->amount)->all()),
        ]);
    }

    /** @return array<string, mixed> */
    private function assignmentOptions(): array {
        return [
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(),
            'tariffs' => ClubFeeTariff::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    /**
     * Kunden ohne Beitragskonto zur Auswahl.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Customer>
     */
    private function freeCustomers(): \Illuminate\Database\Eloquent\Collection {
        return Customer::query()->whereDoesntHave('feeAccount')->whereNull('archived_at')->orderBy('name')->limit(500)->get(['id', 'name', 'number']);
    }
}
