<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePaymentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubFeePaymentMethod;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim, ClubFeeDunning, ClubFeePayment};
use App\Models\Finance\{BankAccount, PaymentRun, SepaMandate};
use App\Models\Platform\User;
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\Club\{ClubFeeNoticePdfRenderer, ClubFeePaymentService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Zahlungen, Guthaben, Rücklastschrift, Mahnung und SEPA-Einzug für
 * Beitragsforderungen (Feature 159, MVP-851). Fachlogik im ClubFeePaymentService;
 * Export der Lastschriftdatei über das Finanzmodul (php-financial-formats).
 */
class ClubFeePaymentController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubFeePaymentService $payments,
    ) {}

    // ── Zahlungen ────────────────────────────────────────────────────────

    public function createPayment(ClubFeeAccount $account, ?ClubFeeClaim $claim = null): View {
        Gate::authorize('update', $account);

        return view('club.fees.accounts._payment_dialog', [
            'account' => $account,
            'claim' => $claim,
            'claims' => ClubFeeClaim::query()->where('club_fee_account_id', $account->id)->open()->orderBy('due_on')->get()->filter(fn(ClubFeeClaim $c): bool => $c->openAmount()->isPositive())->values(),
            'methods' => ClubFeePaymentMethod::cases(),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function storePayment(Request $request, ClubFeeAccount $account): RedirectResponse {
        Gate::authorize('update', $account);
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', 'string', \Illuminate\Validation\Rule::enum(ClubFeePaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:140'],
            'note' => ['nullable', 'string', 'max:255'],
            'claim_id' => ['nullable', 'string', 'max:64'],
        ]);
        $data['claim_id'] = isset($data['claim_id']) && $data['claim_id'] !== '' ? Sqid::decodeOrNumeric(ClubFeeClaim::class, (string) $data['claim_id']) : null;
        /** @var User $actor */
        $actor = Auth::user();
        $created = $this->payments->recordPayment($account, $data, $actor);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.payment_recorded', ['count' => $created->count()]));
    }

    public function applyCredit(ClubFeeAccount $account): RedirectResponse {
        Gate::authorize('update', $account);
        /** @var User $actor */
        $actor = Auth::user();
        $applied = $this->payments->applyCredit($account, $actor);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.credit_applied', ['count' => $applied->count()]));
    }

    public function chargebackDialog(ClubFeeAccount $account, ClubFeePayment $payment): View {
        Gate::authorize('update', $account);
        abort_unless($payment->club_fee_account_id === $account->id, 404);

        return view('club.fees.accounts._chargeback_dialog', ['account' => $account, 'payment' => $payment->load('claim')]);
    }

    public function chargeback(Request $request, ClubFeeAccount $account, ClubFeePayment $payment): RedirectResponse {
        Gate::authorize('update', $account);
        abort_unless($payment->club_fee_account_id === $account->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'bank_fee' => ['nullable', 'string', 'max:20']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->payments->chargeback($payment, (string) $data['reason'], $actor, $data['bank_fee'] ?? null);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.chargeback_recorded'));
    }

    public function unblockCollection(ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->payments->unblockCollection($claim, $actor);

        return redirect()->route('club.fees.claims.show', $claim)->with('success', __('club.fees.flash.collection_unblocked'));
    }

    // ── Mahnung ──────────────────────────────────────────────────────────

    public function dunningDialog(ClubFeeClaim $claim): View {
        Gate::authorize('create', ClubFeeAccount::class);
        $claim->load('account');

        return view('club.fees.claims._dunning_dialog', [
            'claim' => $claim,
            'level' => $claim->dunning_level + 1,
            'email' => (string) ($claim->payer_snapshot['email'] ?? $claim->account->email ?? ''),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function dun(Request $request, ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate([
            'pay_until' => ['nullable', 'date'],
            'fee' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
            'send_mail' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        /** @var User $actor */
        $actor = Auth::user();
        $dunning = $this->payments->dun($claim, $data, $actor);
        if ((bool) ($data['send_mail'] ?? false)) {
            $this->payments->sendDunning($claim, $dunning, (string) ($data['email'] ?? ''), $actor);
        }

        return redirect()->route('club.fees.claims.show', $claim)->with('success', __('club.fees.flash.dunned', ['level' => $dunning->level]));
    }

    public function dunningPdf(ClubFeeClaim $claim, ClubFeeDunning $dunning): Response {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        abort_unless($dunning->club_fee_claim_id === $claim->id, 404);
        $renderer = app(ClubFeeNoticePdfRenderer::class);

        return response($renderer->output($claim, $dunning), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($claim, $dunning) . '"',
        ]);
    }

    public function blockDunningDialog(ClubFeeClaim $claim): View {
        Gate::authorize('create', ClubFeeAccount::class);

        return view('club.fees.claims._block_dialog', ['claim' => $claim]);
    }

    public function blockDunning(Request $request, ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->payments->blockDunning($claim, (string) $data['reason'], $actor);

        return redirect()->route('club.fees.claims.show', $claim)->with('success', __('club.fees.flash.dunning_blocked'));
    }

    public function unblockDunning(ClubFeeClaim $claim): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        /** @var User $actor */
        $actor = Auth::user();
        $this->payments->unblockDunning($claim, $actor);

        return redirect()->route('club.fees.claims.show', $claim)->with('success', __('club.fees.flash.dunning_unblocked'));
    }

    // ── SEPA-Einzug ──────────────────────────────────────────────────────

    public function collections(): View {
        Gate::authorize('viewAny', ClubFeeAccount::class);
        $proposals = $this->payments->collectionProposals($this->currentOrganization());

        return view('club.fees.collections.index', [
            'proposals' => $proposals,
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('label')->get(),
            'runs' => PaymentRun::query()->where('kind', \App\Enums\Finance\PaymentRunKind::DirectDebit->value)->whereHas('items', fn($q) => $q->whereNotNull('sepa_mandate_id')->whereNull('incoming_einvoice_id'))->orderByDesc('id')->limit(20)->get(),
            'formatsAvailable' => FinancialFormatsSupport::isAvailable(),
            'today' => CarbonImmutable::today(),
            'canManage' => Gate::allows('create', ClubFeeAccount::class),
        ]);
    }

    public function storeCollection(Request $request): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate([
            'bank_account_id' => ['required', 'string', 'max:64'],
            'claim_ids' => ['required', 'array', 'min:1'],
            'claim_ids.*' => ['string', 'max:64'],
            'execution_date' => ['nullable', 'date'],
        ]);
        $bankAccountId = Sqid::decodeOrNumeric(BankAccount::class, (string) $data['bank_account_id']);
        $bankAccount = $bankAccountId !== null ? BankAccount::query()->find($bankAccountId) : null;
        abort_if($bankAccount === null, 404);
        $claimIds = array_values(array_filter(array_map(static fn(string $sqid): ?int => Sqid::decodeOrNumeric(ClubFeeClaim::class, $sqid), array_map('strval', $data['claim_ids']))));
        /** @var User $actor */
        $actor = Auth::user();
        $run = $this->payments->createCollectionRun($bankAccount, $actor, $claimIds, isset($data['execution_date']) ? CarbonImmutable::parse((string) $data['execution_date']) : null);

        return redirect()->route('club.fees.collections.index')->with('success', __('club.fees.flash.collection_created', ['count' => $run->items()->count()]));
    }

    public function cancelCollection(PaymentRun $run): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $this->payments->cancelCollectionRun($run);

        return redirect()->route('club.fees.collections.index')->with('success', __('club.fees.flash.collection_cancelled'));
    }

    public function settleCollection(Request $request, PaymentRun $run): RedirectResponse {
        Gate::authorize('create', ClubFeeAccount::class);
        $data = $request->validate(['paid_on' => ['required', 'date']]);
        /** @var User $actor */
        $actor = Auth::user();
        $count = $this->payments->settleCollectionRun($run, CarbonImmutable::parse((string) $data['paid_on']), $actor);

        return redirect()->route('club.fees.collections.index')->with('success', __('club.fees.flash.collection_settled', ['count' => $count]));
    }

    public function accountSettingsDialog(ClubFeeAccount $account): View {
        Gate::authorize('update', $account);

        return view('club.fees.accounts._account_settings_dialog', [
            'account' => $account,
            'mandates' => SepaMandate::query()->where('customer_id', $account->customer_id)->orderByDesc('id')->get(),
            'users' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Mandat und Portalzugang am Beitragskonto festlegen. */
    public function accountSettings(Request $request, ClubFeeAccount $account): RedirectResponse {
        Gate::authorize('update', $account);
        $data = $request->validate(['sepa_mandate_id' => ['nullable', 'string', 'max:64'], 'user_id' => ['nullable', 'string', 'max:64']]);
        $mandateId = isset($data['sepa_mandate_id']) && $data['sepa_mandate_id'] !== '' ? Sqid::decodeOrNumeric(SepaMandate::class, (string) $data['sepa_mandate_id']) : null;
        $userId = isset($data['user_id']) && $data['user_id'] !== '' ? Sqid::decodeOrNumeric(User::class, (string) $data['user_id']) : null;
        if ($mandateId !== null && ! SepaMandate::query()->whereKey($mandateId)->where('customer_id', $account->customer_id)->exists()) {
            return back()->withErrors(['sepa_mandate_id' => __('club.fees.error.mandate_foreign')]);
        }
        if ($userId !== null && ! User::query()->whereKey($userId)->where('organization_id', $account->organization_id)->exists()) {
            return back()->withErrors(['user_id' => __('club.error.user_foreign')]);
        }
        $account->update(['sepa_mandate_id' => $mandateId, 'user_id' => $userId]);
        $account->audit('club.fee.accountUpdated', ['sepa_mandate_id' => $mandateId, 'user_id' => $userId]);

        return redirect()->route('club.fees.accounts.show', $account)->with('success', __('club.fees.flash.account_saved'));
    }
}
