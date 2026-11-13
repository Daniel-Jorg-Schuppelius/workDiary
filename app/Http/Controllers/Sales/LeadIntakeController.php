<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\Sales\{IntakeRiskLevel, LeadIntakeDecision, LeadIntakeStage};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Attachments\FileAttacher;
use App\Services\Sales\Intake\{IntakeKeys, IntakePackages, LeadIntakePdfRenderer, LeadIntakeService, LeadIntakeVisibility};
use App\Support\{ErrorText, SortableQuery};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;
use RuntimeException;

/**
 * Aufnahmeakte am Lead (Feature 200, MVP-1641–1644): Übersicht der Aufnahmen,
 * Akte mit Bogen (Speichern je Feld über den gemeinsamen Endpunkt aus
 * Feature 203, Gesprächsmodus), Stufen, Unterlagen, Entscheidung mit zweitem
 * Blick, Aktualisierung als neue Fassung, PDF und Ablage, Einstellungen der
 * Prüfpakete und Ende der Geschäftsbeziehung am Kunden. Modul-Gate über
 * `leads.*` (Vertriebsmodul).
 */
final class LeadIntakeController extends Controller {
    use ResolvesCurrentOrganization;

    /** Filter der Übersicht: offene Stufen, „Aktualisierung fällig“, alle. */
    public const array STATUS_FILTERS = ['open', 'refresh', 'all'];

    public function __construct(
        private readonly LeadIntakeService $intakes,
        private readonly LeadIntakeVisibility $visibility,
    ) {}

    public function index(Request $request, IntakePackages $packages): View {
        Gate::authorize('viewAny', LeadIntake::class);
        $organization = $this->currentOrganization();
        $stage = LeadIntakeStage::tryFrom((string) $request->query('stage', ''));
        $status = in_array($request->query('status'), self::STATUS_FILTERS, true) ? (string) $request->query('status') : 'open';

        $base = LeadIntake::query()->where('organization_id', $organization->id)->whereNull('superseded_at');
        $query = (clone $base)->with(['lead:id,organization_id,company,contact_name,status,customer_id', 'decider:id,name']);
        if ($stage !== null) {
            $query->where('stage', $stage->value);
        } elseif ($status === 'open') {
            $query->whereIn('stage', LeadIntakeStage::openValues());
        } elseif ($status === 'refresh') {
            $query->whereNotNull('refresh_due_on')->where('refresh_due_on', '<', \App\Support\Query\DateRange::dayAfter(CarbonImmutable::today()->addDays(30)));
        }
        [$sort, $dir] = SortableQuery::resolve($request, ['updated_at', 'refresh_due_on', 'stage'], 'updated_at', 'desc');
        $intakes = $query->orderBy($sort, $dir)->orderByDesc('id')->paginate(25)->withQueryString();

        return view('leads.intakes.index', [
            'intakes' => $intakes,
            'counts' => (clone $base)->selectRaw('stage, COUNT(*) AS n')->groupBy('stage')->pluck('n', 'stage'),
            'stage' => $stage,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
            'enabled' => $packages->enabled($organization),
            'canManage' => Gate::allows('manageSettings', LeadIntake::class),
        ]);
    }

    /** Akte am Lead öffnen (mit den eingeschalteten Prüfpaketen). */
    public function store(Lead $lead): RedirectResponse {
        $this->assertSameOrganization($lead);
        Gate::authorize('create', [LeadIntake::class, $lead]);

        try {
            $intake = $this->intakes->open($lead, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('leads.intakes.show', [$lead, $intake])->with('success', __('lead_intake.flash.opened'));
    }

    public function show(Lead $lead, LeadIntake $intake): View {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('view', $intake);
        $user = $this->authUser();
        $intake->loadMissing(['decider:id,name', 'secondReviewer:id,name', 'protocol:id,title', 'customer:id,name,slug']);
        $canEdit = Gate::allows('autosave', $intake);

        $documents = [];
        foreach ($intake->attachments()->whereNotNull('meta_type')->orderBy('id')->get() as $attachment) {
            if (! str_starts_with((string) $attachment->meta_type, IntakeKeys::ATTACHMENT_META)) {
                continue;
            }
            $key = substr((string) $attachment->meta_type, strlen(IntakeKeys::ATTACHMENT_META));
            if ($this->visibility->canSeeKey($intake, $user, $key)) {
                $documents[$key][] = $attachment;
            }
        }
        $documentFields = [];
        foreach ($this->visibility->schemaFor($intake, $user) as $field) {
            if (IntakeKeys::isDocument($field->key)) {
                $documentFields[] = $field;
            }
        }

        return view('leads.intakes.show', [
            'lead' => $lead,
            'intake' => $intake,
            'schema' => $this->visibility->schemaFor($intake, $user),
            'values' => $this->visibility->valuesFor($intake, $user),
            'hiddenAnswered' => $this->visibility->hiddenAnswered($intake, $user),
            'hiddenCount' => count($this->visibility->hiddenFor($intake, $user)),
            'documents' => $documents,
            'documentFields' => $documentFields,
            'versions' => $this->intakes->versions($lead),
            'canEdit' => $canEdit,
            'canDecide' => Gate::allows('decide', $intake),
            'canRefresh' => Gate::allows('refresh', $intake),
            'canArchive' => Gate::allows('archive', $intake),
            'autosave' => $canEdit ? [
                'url' => route('field-documents.autosave', ['type' => $intake->getMorphClass(), 'document' => $intake->sqid, 'field' => '__FIELD__']),
                'version' => $intake->version,
                'reload_url' => route('leads.intakes.show', [$lead, $intake]),
            ] : null,
            'acceptanceErrors' => $intake->acceptsFieldAutosave() ? $this->visibleErrors($intake, $this->intakes->acceptanceErrors($intake)) : [],
        ]);
    }

    /** Ganzen Bogen speichern (auch ohne JavaScript). */
    public function save(Request $request, Lead $lead, LeadIntake $intake): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('update', $intake);

        try {
            $this->intakes->saveValues($intake, (array) $request->input('values', []), (array) $request->input('notes', []), $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('lead_intake.flash.saved'));
    }

    public function stage(Request $request, Lead $lead, LeadIntake $intake): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('update', $intake);
        $data = $request->validate(['stage' => ['required', Rule::enum(LeadIntakeStage::class)]]);

        try {
            $this->intakes->moveTo($intake, LeadIntakeStage::from((string) $data['stage']), $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('lead_intake.flash.stage', ['stage' => LeadIntakeStage::from((string) $data['stage'])->label()]));
    }

    /** Dialog „Entscheiden“. */
    public function decision(Lead $lead, LeadIntake $intake): View {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('decide', $intake);

        return view('leads.intakes._decision_dialog', [
            'lead' => $lead,
            'intake' => $intake,
            'errors_before' => $intake->acceptsFieldAutosave() ? $this->visibleErrors($intake, $this->intakes->acceptanceErrors($intake)) : [],
        ]);
    }

    public function decide(Request $request, Lead $lead, LeadIntake $intake): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('decide', $intake);
        $data = $request->validate([
            'decision' => ['required', Rule::enum(LeadIntakeDecision::class)],
            'reason' => ['required', 'string', 'max:' . LeadIntakeService::REASON_MAX],
        ]);
        $decision = LeadIntakeDecision::from((string) $data['decision']);

        try {
            $this->intakes->decide($intake, $decision, (string) $data['reason'], $this->authUser());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages($this->visibleErrors($intake, array_map(static fn (array $messages): string => (string) ($messages[0] ?? ''), $e->errors())));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('leads.intakes.show', [$lead, $intake])->with('success', __('lead_intake.flash.decided', ['decision' => $decision->label()]));
    }

    /** Zweiter Blick: bestätigen oder mit Begründung zurückgeben. */
    public function secondReview(Request $request, Lead $lead, LeadIntake $intake): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('decide', $intake);
        $data = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'return'])],
            'reason' => ['nullable', 'string', 'max:' . LeadIntakeService::REASON_MAX],
        ]);

        try {
            if ($data['action'] === 'confirm') {
                $this->intakes->confirmSecondReview($intake, $this->authUser());
            } else {
                $this->intakes->returnFromSecondReview($intake, (string) ($data['reason'] ?? ''), $this->authUser());
            }
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __($data['action'] === 'confirm' ? 'lead_intake.flash.second_review_confirmed' : 'lead_intake.flash.second_review_returned'));
    }

    /** Datei zu einer Unterlage ablegen. */
    public function document(Request $request, Lead $lead, LeadIntake $intake): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('update', $intake);
        $data = $request->validate([
            'document' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'max:' . FileAttacher::maxKbFor($intake)],
        ]);

        try {
            $this->intakes->addDocument($intake, (string) $data['document'], $request->file('file'), $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('lead_intake.flash.document_added'));
    }

    /** Aktualisierung als neue Fassung (MVP-1643). */
    public function refresh(Lead $lead, LeadIntake $intake): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('refresh', $intake);

        try {
            $new = $this->intakes->refresh($intake, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('leads.intakes.show', [$lead, $new])->with('success', __('lead_intake.flash.refreshed', ['revision' => $new->revision]));
    }

    /** PDF im Papierlayout — enge Angaben nur mit eigenem Recht (MVP-1644). */
    public function pdf(Lead $lead, LeadIntake $intake, LeadIntakePdfRenderer $renderer): Response {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('view', $intake);
        $pdf = $renderer->output($intake, $this->authUser());
        $intake->audit('lead_intake.pdf_downloaded', ['revision' => $intake->revision]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($intake) . '"',
        ]);
    }

    /** PDF ohne enge Angaben als Dokument am Kunden bzw. Lead ablegen. */
    public function archive(Lead $lead, LeadIntake $intake, LeadIntakePdfRenderer $renderer): RedirectResponse {
        $this->assertBelongs($lead, $intake);
        Gate::authorize('archive', $intake);

        try {
            $attachment = $renderer->archive($intake, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('lead_intake.flash.archived', ['file' => $attachment->original_name]));
    }

    /** Dialog „Einstellungen der Aufnahmen“. */
    public function settings(IntakePackages $packages): View {
        Gate::authorize('manageSettings', LeadIntake::class);
        $organization = $this->currentOrganization();

        return view('leads.intakes._settings_dialog', [
            'available' => array_map(static fn ($package): string => $package->label(), $packages->available($organization)),
            'enabled' => $packages->enabledKeys($organization),
            'refreshMonths' => $packages->riskRefreshMonths($organization),
            'secondReview' => $packages->secondReview($organization),
        ]);
    }

    public function updateSettings(Request $request, IntakePackages $packages): RedirectResponse {
        Gate::authorize('manageSettings', LeadIntake::class);
        $organization = $this->currentOrganization();
        $levels = array_map(static fn (IntakeRiskLevel $level): string => $level->value, IntakeRiskLevel::cases());
        $data = $request->validate([
            'packages' => ['nullable', 'array'],
            'packages.*' => ['string', Rule::in(array_keys($packages->available($organization)))],
            'refresh_months' => ['required', 'array'],
            'refresh_months.*' => ['required', 'integer', 'min:1', 'max:' . IntakePackages::MAX_REFRESH_MONTHS],
            'second_review' => ['nullable', 'boolean'],
        ]);
        $packages->setEnabled($organization, array_values(array_map('strval', (array) ($data['packages'] ?? []))));
        $months = [];
        foreach ($levels as $level) {
            $months[$level] = (int) ($data['refresh_months'][$level] ?? IntakePackages::DEFAULT_REFRESH_MONTHS);
        }
        $packages->saveRiskRefreshMonths($organization, $months);
        $packages->setSecondReview($organization, (bool) ($data['second_review'] ?? false));

        return back()->with('success', __('lead_intake.flash.settings_saved'));
    }

    /** Dialog „Ende der Geschäftsbeziehung“ am Kunden. */
    public function relationship(Customer $customer): View {
        $this->assertSameOrganization($customer);
        Gate::authorize('endRelationship', LeadIntake::class);

        return view('leads.intakes._relationship_dialog', ['customer' => $customer]);
    }

    public function updateRelationship(Request $request, Customer $customer): RedirectResponse {
        $this->assertSameOrganization($customer);
        Gate::authorize('endRelationship', LeadIntake::class);
        $data = $request->validate(['relationship_ended_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $parsed = isset($data['relationship_ended_on']) ? DateHelper::parseFlexible((string) $data['relationship_ended_on']) : null;

        $count = $this->intakes->endRelationship($customer, $parsed !== null ? CarbonImmutable::instance($parsed)->startOfDay() : null, $this->authUser());

        return back()->with('success', __('lead_intake.flash.relationship_saved', ['count' => $count]));
    }

    private function assertBelongs(Lead $lead, LeadIntake $intake): void {
        $this->assertSameOrganization($lead);
        abort_unless((int) $intake->lead_id === (int) $lead->id, 404);
    }

    /**
     * Meldungen zu verborgenen Feldern ohne Inhalt: nur, dass eine Angabe mit
     * eigenem Recht fehlt.
     *
     * @param  array<string, string>  $errors
     * @return array<string, string>
     */
    private function visibleErrors(LeadIntake $intake, array $errors): array {
        $user = $this->authUser();
        $out = [];
        foreach ($errors as $key => $message) {
            $restriction = $intake->restrictionFor($key, $user);
            $out[$key] = $restriction !== null ? (string) __('lead_intake.error.restricted_missing', ['right' => $restriction]) : $message;
        }

        return $out;
    }
}
