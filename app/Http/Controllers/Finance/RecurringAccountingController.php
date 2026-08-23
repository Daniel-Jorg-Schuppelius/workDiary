<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringAccountingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{RecurringInterval, RecurringTemplateKind, RecurringTemplateStatus};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingRecurringRun, AccountingRecurringTemplate};
use App\Models\InvoiceSchedule;
use App\Services\Accounting\RecurringAccountingService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Wiederkehrende Vorgänge (Feature 125, MVP-675).
 *
 * Die Seite zeigt drei Arten nebeneinander: die vorhandenen Serienrechnungen
 * (nur verlinkt — sie bleiben beim `InvoiceSchedule`), Belegerwartungen und
 * Buchungsvorlagen.
 */
class RecurringAccountingController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly RecurringAccountingService $recurring) {}

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting.recurring', [
            'templates' => AccountingRecurringTemplate::query()
                ->where('organization_id', $organization->id)
                ->with(['responsible', 'supplier'])
                ->withCount(['runs as open_runs_count' => fn ($query) => $query->whereIn('status', ['expected', 'draft_created'])])
                ->orderBy('next_due_on')
                ->get(),
            'openRuns' => AccountingRecurringRun::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', ['expected', 'draft_created', 'blocked'])
                ->with(['template', 'entry'])
                ->orderBy('due_on')
                ->get(),
            // Serienrechnungen bleiben, wo sie sind — hier nur sichtbar gemacht.
            'invoiceSchedules' => InvoiceSchedule::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->orderBy('next_run_on')
                ->get(),
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
            'canPrepare' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    public function form(?AccountingRecurringTemplate $template = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._recurring_dialog', [
            'template' => $template,
            'kinds' => RecurringTemplateKind::cases(),
            'intervals' => RecurringInterval::cases(),
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->active()
                ->orderBy('number')
                ->get(),
            'preview' => $template !== null ? $this->recurring->preview($template) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $data = $this->validated($request, $organization);
        $template = AccountingRecurringTemplate::query()->create($data + [
            'organization_id' => $organization->id,
            'status' => RecurringTemplateStatus::Active,
            'version' => 1,
        ]);

        $template->update(['next_due_on' => $this->recurring->firstDue($template)->toDateString()]);

        return back()->with('status', __('accounting.recurring.flash.saved'));
    }

    /**
     * Änderungen versionieren die Vorlage: Bereits erzeugte Vorgänge behalten
     * ihre Fassung, künftige laufen mit der neuen.
     */
    public function update(Request $request, AccountingRecurringTemplate $template): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $this->assertSameOrganization($template);

        $data = $this->validated($request, $this->currentOrganizationOrAbort());
        $template->update($data + ['version' => $template->version + 1]);

        return back()->with('status', __('accounting.recurring.flash.versioned'));
    }

    public function pause(AccountingRecurringTemplate $template): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $this->assertSameOrganization($template);
        $this->recurring->pause($template);

        return back()->with('status', __('accounting.recurring.flash.paused'));
    }

    public function resume(AccountingRecurringTemplate $template): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $this->assertSameOrganization($template);
        $this->recurring->resume($template);

        return back()->with('status', __('accounting.recurring.flash.resumed'));
    }

    public function end(AccountingRecurringTemplate $template): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $this->assertSameOrganization($template);
        $this->recurring->end($template);

        return back()->with('status', __('accounting.recurring.flash.ended'));
    }

    /** Lauf von Hand auslösen (Nachholen), ohne auf den Scheduler zu warten. */
    public function run(Request $request, AccountingRecurringTemplate $template): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $this->assertSameOrganization($template);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->recurring->runOnce($template, $actor);

        return back()->with('status', __('accounting.recurring.flash.ran'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, \App\Models\Organization $organization): array {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:' . implode(',', array_column(RecurringTemplateKind::cases(), 'value'))],
            'name' => ['required', 'string', 'max:191'],
            'interval' => ['required', 'string', 'in:' . implode(',', array_column(RecurringInterval::cases(), 'value'))],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'expected_amount' => ['nullable', 'numeric', 'gte:0'],
            'debit_account' => ['nullable', 'string'],
            'credit_account' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $kind = RecurringTemplateKind::from((string) $data['kind']);
        $lines = null;

        if ($kind->createsDraft()) {
            // Ohne Zeilen bliebe der Lauf blockiert — das melden wir sofort,
            // statt es erst nachts im Scheduler herauszufinden.
            if (empty($data['debit_account']) || empty($data['credit_account']) || ! isset($data['expected_amount'])) {
                abort(422, (string) __('accounting.recurring.error.template_incomplete'));
            }

            $debitId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['debit_account']);
            $creditId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['credit_account']);
            $own = AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->whereIn('id', [$debitId, $creditId])
                ->count();
            abort_unless($own === count(array_unique([$debitId, $creditId])), 422);

            $amount = number_format((float) $data['expected_amount'], 2, '.', '');
            $lines = [
                ['accounting_account_id' => $debitId, 'debit' => $amount, 'credit' => '0.00'],
                ['accounting_account_id' => $creditId, 'debit' => '0.00', 'credit' => $amount],
            ];
        }

        return [
            'kind' => $kind,
            'name' => (string) $data['name'],
            'interval' => RecurringInterval::from((string) $data['interval']),
            'due_day' => (int) $data['due_day'],
            'starts_on' => (string) $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'expected_amount' => isset($data['expected_amount'])
                ? number_format((float) $data['expected_amount'], 2, '.', '')
                : null,
            'template_lines' => $lines,
            'note' => $data['note'] ?? null,
        ];
    }

    private function assertSameOrganization(AccountingRecurringTemplate $template): void {
        abort_unless((int) $template->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
    }
}
