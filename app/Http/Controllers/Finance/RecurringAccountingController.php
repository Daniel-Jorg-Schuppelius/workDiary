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

use App\Enums\Finance\{RecurringInterval, RecurringRunStatus, RecurringTemplateKind, RecurringTemplateStatus};
use App\Enums\Invoicing\InvoiceScheduleStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingRecurringRun, AccountingRecurringTemplate};
use App\Models\Invoicing\InvoiceSchedule;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\RecurringAccountingService;
use App\Support\Sqid;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
                ->where('status', InvoiceScheduleStatus::Active)
                ->orderBy('next_run_on')
                ->get(),
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
            'canPrepare' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    public function form(?AccountingRecurringTemplate $template = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $accounts = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->orderBy('number')
            ->get();

        // Die Kontenfelder spiegeln die gespeicherten Zeilen — sonst stünde
        // beim Bearbeiten „kein Konto" da und das Speichern würde abgewiesen.
        $lineAccount = static function (string $side) use ($template, $accounts): ?string {
            foreach ($template === null ? [] : ($template->template_lines ?? []) as $line) {
                if (! Decimal::of((string) ($line[$side] ?? '0'), 2)->isZero()) {
                    return $accounts->firstWhere('id', (int) ($line['accounting_account_id'] ?? 0))?->sqid;
                }
            }

            return null;
        };

        return view('finance.accounting._recurring_dialog', [
            'template' => $template,
            'kinds' => RecurringTemplateKind::cases(),
            'intervals' => RecurringInterval::cases(),
            'accounts' => $accounts,
            'debitAccount' => $lineAccount('debit'),
            'creditAccount' => $lineAccount('credit'),
            'users' => User::query()
                ->forOrganization($organization)
                ->where(fn ($query) => $query->whereNull('deactivated_at')->orWhere('id', $template?->responsible_user_id))
                ->orderBy('name')
                ->get(['id', 'name']),
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

    /** Belegerwartung erfüllen: die eingegangene Rechnung zuordnen. */
    public function fulfillForm(Request $request, AccountingRecurringRun $run): View {
        $actor = $this->assertFulfillable($request, $run);

        return view('finance.accounting._recurring_fulfill_dialog', [
            'run' => $run,
            'candidates' => $this->recurring->fulfillmentCandidates($run, $actor),
        ]);
    }

    public function fulfill(Request $request, AccountingRecurringRun $run): RedirectResponse {
        $actor = $this->assertFulfillable($request, $run);
        $data = $request->validate(['incoming_einvoice_id' => ['required', 'string']]);

        // Nur, was der Dialog anbietet: eigene Organisation, sichtbar, nicht
        // abgelehnt, noch keinem anderen Vorgang zugeordnet.
        $incoming = $this->recurring->fulfillmentCandidates($run, $actor)
            ->firstWhere('sqid', (string) $data['incoming_einvoice_id'])
            ?? throw ValidationException::withMessages([
                'incoming_einvoice_id' => (string) __('validation.exists', ['attribute' => __('validation.attributes.incoming_einvoice_id')]),
            ]);

        $this->recurring->fulfill($run, $incoming);

        return back()->with('status', __('accounting.recurring.flash.fulfilled'));
    }

    private function assertFulfillable(Request $request, AccountingRecurringRun $run): User {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        abort_unless((int) $run->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
        // Den Vorgang einer Buchungsvorlage schließt die Festschreibung ihres Entwurfs.
        abort_unless(
            $run->status === RecurringRunStatus::Expected
                && $run->template?->kind === RecurringTemplateKind::DocumentExpectation,
            404,
        );

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Organization $organization): array {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:' . implode(',', array_column(RecurringTemplateKind::cases(), 'value'))],
            'name' => ['required', 'string', 'max:191'],
            'interval' => ['required', 'string', 'in:' . implode(',', array_column(RecurringInterval::cases(), 'value'))],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'expected_amount' => ['nullable', 'numeric', 'decimal:0,8', 'gte:0'],
            'debit_account' => ['nullable', 'string'],
            'credit_account' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $responsibleId = null;
        if (! empty($data['responsible_user_id'])) {
            $responsibleId = Sqid::decodeOrNumeric(User::class, (string) $data['responsible_user_id']);
            if ($responsibleId === null || ! User::query()->forOrganization($organization)->whereKey($responsibleId)->exists()) {
                throw ValidationException::withMessages([
                    'responsible_user_id' => (string) __('validation.exists', ['attribute' => __('validation.attributes.responsible_user_id')]),
                ]);
            }
        }

        $kind = RecurringTemplateKind::from((string) $data['kind']);
        $lines = null;

        if ($kind->createsDraft()) {
            // Ohne Zeilen bliebe der Lauf blockiert — das melden wir sofort,
            // statt es erst nachts im Scheduler herauszufinden.
            $missing = array_filter(
                ['debit_account', 'credit_account', 'expected_amount'],
                static fn (string $field): bool => ! isset($data[$field]) || $data[$field] === '',
            );
            if ($missing !== []) {
                throw ValidationException::withMessages(array_fill_keys(
                    $missing,
                    (string) __('accounting.recurring.error.template_incomplete'),
                ));
            }

            $debitId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['debit_account']);
            $creditId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['credit_account']);
            $own = AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->whereIn('id', [$debitId, $creditId])
                ->count();
            abort_unless($own === count(array_unique([$debitId, $creditId])), 422);

            $amount = Decimal::of((string) $data['expected_amount'], 2)->getValue();
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
                ? Decimal::of((string) $data['expected_amount'], 2)->getValue()
                : null,
            'template_lines' => $lines,
            'responsible_user_id' => $responsibleId,
            'note' => $data['note'] ?? null,
        ];
    }

    private function assertSameOrganization(AccountingRecurringTemplate $template): void {
        abort_unless((int) $template->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
    }
}
