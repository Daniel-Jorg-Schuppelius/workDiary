<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeClaimController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubFeeClaimStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim};
use App\Models\Platform\User;
use App\Services\Club\{ClubFeeNoticePdfRenderer, ClubFeeRunService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Beitragsforderungen und offene Posten (Feature 159, MVP-850): Liste mit
 * Status/Überfälligkeit, Beleg mit Positionen, Mitteilung (PDF/Mail mit
 * Zustellnachweis), Storno und Korrektur.
 */
class ClubFeeClaimController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubFeeRunService $runs,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $status = (string) $request->query('status', 'open');
        $q = trim((string) $request->query('q', ''));
        $accountId = Sqid::decodeOrNumeric(ClubFeeAccount::class, (string) $request->query('account', ''));
        $today = CarbonImmutable::today();

        $claims = ClubFeeClaim::query()
            ->with(['account:id,name', 'correctedClaim:id,number'])
            ->when($status === 'open', fn($query) => $query->open())
            ->when($status === 'overdue', fn($query) => $query->open()->where('due_on', '<', \App\Support\Query\DateRange::day($today)))
            ->when(in_array($status, ClubFeeClaimStatus::values(), true), fn($query) => $query->where('status', $status))
            ->when($accountId !== null, fn($query) => $query->where('club_fee_account_id', $accountId))
            ->when($q !== '', fn($query) => $query->where(fn($inner) => $inner->whereLikeEscaped('number', $q)->orWhereHas('account', fn($a) => $a->whereLikeEscaped('name', $q))))
            ->orderByDesc('sequence')
            ->paginate(50)
            ->withQueryString();

        $open = ClubFeeClaim::query()->open()->get();

        return view('club.fees.claims.index', [
            'claims' => $claims,
            'filters' => ['status' => $status, 'q' => $q, 'account' => $accountId !== null ? Sqid::encode(ClubFeeAccount::class, $accountId) : ''],
            'statuses' => ClubFeeClaimStatus::cases(),
            'openTotal' => $open->isEmpty() ? null : Money::sum($open->map(fn(ClubFeeClaim $c): Money => $c->openAmount())->all()),
            'overdueCount' => $open->filter(fn(ClubFeeClaim $c): bool => $c->isOverdue($today))->count(),
            'today' => $today,
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function show(ClubFeeClaim $claim): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $claim->load(['items.member', 'account.customer', 'run', 'correctedClaim', 'corrections', 'cancelledBy:id,name']);

        return view('club.fees.claims.show', [
            'claim' => $claim,
            'dispatches' => $claim->dispatches()->limit(50)->get(),
            // Zahlungen und Mahnhistorie (MVP-851).
            'payments' => \App\Models\Club\ClubFeePayment::query()->where('club_fee_claim_id', $claim->id)->with('createdBy:id,name')->orderByDesc('paid_on')->orderByDesc('id')->get(),
            'dunnings' => \App\Models\Club\ClubFeeDunning::query()->where('club_fee_claim_id', $claim->id)->with('createdBy:id,name')->orderByDesc('level')->get(),
            'canDun' => Gate::allows('create', ClubFeeAccount::class) && $claim->isOverdue() && ! $claim->isDunningBlocked() && $claim->dunning_level < \App\Services\Club\ClubFeePaymentService::MAX_DUNNING_LEVEL,
            'today' => CarbonImmutable::today(),
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function pdf(ClubFeeClaim $claim): Response {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $renderer = app(ClubFeeNoticePdfRenderer::class);
        /** @var User|null $actor */
        $actor = Auth::user();
        $this->runs->recordDownload($claim, $actor);

        return response($renderer->output($claim), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($claim) . '"',
        ]);
    }

    public function sendDialog(ClubFeeClaim $claim): View {
        Gate::authorize('create', ClubFeeAccount::class);
        $claim->load('account');

        return view('club.fees.claims._send_dialog', ['claim' => $claim, 'email' => (string) ($claim->payer_snapshot['email'] ?? $claim->account->email ?? '')]);
    }

    public function send(Request $request, ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->runs->sendNotice($claim, (string) $data['email'], $actor);

        return redirect()->route('club.fees.claims.show', $claim)->with('success', __('club.fees.flash.notice_queued', ['email' => $data['email']]));
    }

    public function cancelDialog(ClubFeeClaim $claim): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.claims._cancel_dialog', ['claim' => $claim]);
    }

    public function cancel(Request $request, ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->runs->cancelClaim($claim, $actor, (string) $data['reason']);

        return redirect()->route('club.fees.claims.show', $claim)->with('success', __('club.fees.flash.claim_cancelled'));
    }

    public function correctionDialog(ClubFeeClaim $claim): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.claims._correction_dialog', ['claim' => $claim]);
    }

    public function storeCorrection(Request $request, ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'label' => ['required', 'string', 'max:160'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $amount = \CommonToolkit\Helper\Data\NumberHelper::normalizeDecimalStringOrNull((string) $data['amount']);
        if ($amount === null) {
            return back()->withErrors(['amount' => __('club.fees.error.amount_invalid')])->withInput();
        }
        /** @var User $actor */
        $actor = Auth::user();
        $correction = $this->runs->createCorrection($claim, $actor, $amount, (string) $data['label'], (string) $data['reason']);

        return redirect()->route('club.fees.claims.show', $correction)->with('success', __('club.fees.flash.correction_created', ['number' => $correction->number]));
    }
}
