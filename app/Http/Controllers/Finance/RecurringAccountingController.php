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
use App\Models\Accounting\{AccountingRecurringRun, AccountingRecurringTemplate};
use App\Models\Invoicing\InvoiceSchedule;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{ManualEntryLines, RecurringAccountingService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
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

    public function __construct(
        private readonly RecurringAccountingService $recurring,
        private readonly ManualEntryLines $manualLines,
    ) {}

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
        if ($template instanceof AccountingRecurringTemplate) {
            $this->assertSameOrganization($template);
        }
        $lines = $template instanceof AccountingRecurringTemplate ? ($template->template_lines ?? []) : [];
        $rows = $this->manualLines->rows($lines);

        return view('finance.accounting._recurring_dialog', [
            'template' => $template,
            'kinds' => RecurringTemplateKind::cases(),
            'intervals' => RecurringInterval::cases(),
            'rows' => $rows,
            'rowCount' => $this->manualLines->rowCount($rows),
            'counterparty' => $this->manualLines->counterpartyValue($lines),
            'users' => User::query()
                ->forOrganization($organization)
                ->where(fn ($query) => $query->whereNull('deactivated_at')->orWhere('id', $template?->responsible_user_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'preview' => $template !== null ? $this->recurring->preview($template) : [],
        ] + $this->manualLines->formOptions($organization, CarbonImmutable::now()));
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

        $data = $this->validated($request, $this->currentOrganizationOrAbort(), $template);
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
    private function validated(Request $request, Organization $organization, ?AccountingRecurringTemplate $template = null): array {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::enum(RecurringTemplateKind::class)],
            'name' => ['required', 'string', 'max:191'],
            'interval' => ['required', 'string', Rule::enum(RecurringInterval::class)],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'expected_amount' => ['nullable', 'numeric', 'decimal:0,8', 'gte:0'],
            'counterparty' => ['nullable', 'string', 'max:80'],
            'rows' => ['nullable', 'array', 'max:60'],
            'rows.*.line' => ['nullable', 'integer', 'min:0'],
            'rows.*.account' => ['nullable', 'string', 'max:64'],
            'rows.*.debit' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'rows.*.credit' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'rows.*.tax_code' => ['nullable', 'string', 'max:64'],
            'rows.*.cost_center' => ['nullable', 'string', 'max:64'],
            'rows.*.memo' => ['nullable', 'string', 'max:191'],
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
            // statt es erst nachts im Scheduler herauszufinden. Die Vorlage bucht
            // mehrzeilig mit Steuerkennzeichen und Gegenpartei wie die Handbuchung (MVP-1558).
            $startsOn = CarbonImmutable::parse((string) $data['starts_on'])->startOfDay();
            $today = CarbonImmutable::now()->startOfDay();
            try {
                $lines = $this->manualLines->build(
                    $organization,
                    $startsOn->greaterThan($today) ? $startsOn : $today,
                    $data['rows'] ?? [],
                    (string) ($data['counterparty'] ?? ''),
                    $template instanceof AccountingRecurringTemplate ? ($template->template_lines ?? []) : [],
                );
            } catch (ValidationException $exception) {
                throw array_key_exists('lines', $exception->errors())
                    ? ValidationException::withMessages(['rows' => (string) __('accounting.recurring.error.template_incomplete')])
                    : $exception;
            }

            $debit = NumberHelper::sumPrecise(array_column($lines, 'debit'), 2);
            if (NumberHelper::comparePrecise($debit, NumberHelper::sumPrecise(array_column($lines, 'credit'), 2), 2) !== 0) {
                throw ValidationException::withMessages([
                    'rows' => (string) __('accounting.recurring.error.template_unbalanced'),
                ]);
            }
            // Erwarteter Betrag ist die Buchungssumme, wenn das Feld leer bleibt.
            $data['expected_amount'] ??= $debit;
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
}
