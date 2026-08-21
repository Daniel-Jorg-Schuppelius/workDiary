<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Finance\{BankAccount, PaymentRun, PaymentRunItem};
use App\Models\IncomingEInvoice;
use App\Services\Finance\FinancialFormatsSupport;
use App\Services\Finance\Sepa\{PaymentProposalService, PaymentRunService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * SEPA-Zahlungsausgang (Feature 120, MVP-609).
 *
 * workDiary erzeugt eine Datei, keinen Zahlungsauftrag — die Autorisierung
 * bleibt im Banking-Programm. Der Export braucht das private Formatpaket
 * (AGENTS.md §9.1); ohne es bleibt der Rest der Seite bedienbar.
 */
class PaymentRunController extends Controller {
    public function __construct(
        private readonly PaymentRunService $runs,
        private readonly PaymentProposalService $proposals,
    ) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        return view('finance.payment-runs.index', [
            'runs' => PaymentRun::query()
                ->with(['bankAccount'])
                ->withCount('items')
                ->orderByDesc('id')
                ->paginate(25),
            'formatsAvailable' => FinancialFormatsSupport::isAvailable(),
        ]);
    }

    /** Zahlungsvorschlag: fällige, freigegebene Eingangsrechnungen. */
    public function proposals(): View {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        return view('finance.payment-runs.proposals', [
            'proposals' => $this->proposals->proposals(),
            'accounts' => BankAccount::query()->where('is_active', true)->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        $data = $request->validate([
            'bank_account' => ['required', 'string'],
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*' => ['string'],
            'execution_date' => ['nullable', 'date'],
            'label' => ['nullable', 'string', 'max:191'],
        ]);

        $actor = $request->user();
        abort_if($actor === null, 403);

        $account = BankAccount::query()->findOrFail(Sqid::decodeOrNumeric(BankAccount::class, (string) $data['bank_account']));
        $ids = array_map(
            static fn (string $value): int => (int) Sqid::decodeOrNumeric(IncomingEInvoice::class, $value),
            array_values($data['invoices']),
        );

        try {
            $run = $this->runs->createFromProposals(
                $account,
                $actor,
                array_values(array_filter($ids)),
                filled($data['execution_date'] ?? null) ? CarbonImmutable::parse((string) $data['execution_date']) : null,
                $data['label'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.payment-runs.show', $run)->with('status', __('sepa.run_created'));
    }

    public function show(PaymentRun $run): View {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        return view('finance.payment-runs.show', [
            'run' => $run->load(['items.incomingEInvoice', 'items.mandate', 'bankAccount', 'releasedBy']),
            'canRelease' => Gate::allows(Permission::FinancePaymentRelease->value),
            'formatsAvailable' => FinancialFormatsSupport::isAvailable(),
        ]);
    }

    public function release(Request $request, PaymentRun $run): RedirectResponse {
        abort_unless(Gate::allows(Permission::FinancePaymentRelease->value), 403);
        $actor = $request->user();
        abort_if($actor === null, 403);

        try {
            $this->runs->release($run, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('sepa.run_released'));
    }

    public function export(Request $request, PaymentRun $run): RedirectResponse|Response {
        abort_unless(Gate::allows(Permission::FinancePaymentRelease->value), 403);
        $actor = $request->user();
        abort_if($actor === null, 403);

        if (! FinancialFormatsSupport::isAvailable()) {
            return back()->with('error', FinancialFormatsSupport::unavailableMessage('sepa.error.unavailable'));
        }

        try {
            $xml = $this->runs->export($run, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . ($run->fresh()->message_id ?? 'sepa') . '.xml"',
        ]);
    }

    public function cancel(PaymentRun $run): RedirectResponse {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);

        try {
            $this->runs->cancel($run);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('sepa.run_cancelled'));
    }

    public function removeItem(PaymentRun $run, PaymentRunItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);
        abort_unless((int) $item->payment_run_id === (int) $run->id, 404);

        try {
            $this->runs->removeItem($item);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('sepa.item_removed'));
    }

    public function adjustForm(PaymentRun $run, PaymentRunItem $item): View {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);
        abort_unless((int) $item->payment_run_id === (int) $run->id, 404);

        return view('finance.payment-runs._adjust_dialog', ['run' => $run, 'item' => $item]);
    }

    public function adjust(Request $request, PaymentRun $run, PaymentRunItem $item): RedirectResponse {
        abort_unless(Gate::allows(Permission::FinancePaymentRun->value), 403);
        abort_unless((int) $item->payment_run_id === (int) $run->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'deduction_reason' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $this->runs->adjustItem($item, (float) $data['amount'], $data['deduction_reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.payment-runs.show', $run)->with('status', __('sepa.item_adjusted'));
    }
}
