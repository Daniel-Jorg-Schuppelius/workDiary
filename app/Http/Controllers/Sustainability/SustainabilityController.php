<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sustainability;

use App\Enums\Sustainability\{SustainabilityAssessmentStatus, SustainabilityMeasureStatus};
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\Platform\User;
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilityAssessment, SustainabilityCriterion, SustainabilityFactorSet, SustainabilityFrameMapping, SustainabilityMeasure, SustainabilityReportSnapshot, SustainabilitySite, SustainabilityTarget};
use App\Services\Sustainability\{EmissionCalculationService, SustainabilityAssessmentService};
use App\Support\{ErrorText, SortableQuery};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;

/**
 * Nachhaltigkeit/ESG (Feature 071, MVP-223–234): Dashboard mit
 * Handlungsfeldern, Kriterienkatalog, Aktivitätsdaten, versionierte
 * Bewertungen, Faktorenbibliothek, Maßnahmen, Ziele, Bericht mit
 * Datenqualitätswarnung + Snapshot und VSME-Referenzmatrix.
 */
class SustainabilityController extends Controller {
    use RendersReportPdf;
    use WritesReportCsv;

    /** Dashboard-Karten zeigen die jüngsten Einträge; die ganze Menge steht in den Listen. */
    private const DASHBOARD_LIMIT = 10;

    private const ASSESSMENT_SORTS = ['subject' => 'subject_label', 'version' => 'version', 'status' => 'status', 'score' => 'total_score', 'assessed_at' => 'assessed_at', 'created' => 'id'];

    private const MEASURE_SORTS = ['title' => 'title', 'status' => 'status', 'due_on' => 'due_on', 'created' => 'id'];

    public function __construct(
        private readonly EmissionCalculationService $emissions,
        private readonly SustainabilityAssessmentService $assessments,
    ) {}

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\Response {
        Gate::authorize('viewAny', SustainabilityAssessment::class);
        $orgId = $this->currentOrganization()->id;

        $from = (string) $request->query('from', now()->startOfYear()->toDateString());
        $to = (string) $request->query('to', now()->toDateString());
        $aggregate = $this->emissions->aggregate($orgId, $from, $to);

        $critical = SustainabilityAssessment::query()->where('rating', 'red')->where('status', SustainabilityAssessmentStatus::Final)->count();
        $openMeasures = SustainabilityMeasure::query()->whereIn('status', SustainabilityMeasureStatus::open())->count();
        $estimatedShare = array_sum($aggregate['quality_share']) > 0
            ? round(($aggregate['quality_share']['estimated'] ?? 0) / array_sum($aggregate['quality_share']) * 100)
            : 0;

        $targets = SustainabilityTarget::query()->get()->map(function (SustainabilityTarget $target) use ($aggregate): array {
            $actual = null;
            if ($target->metric === 'co2e_total') {
                $actual = $aggregate['co2e_total_kg'];
            } elseif ($target->metric === 'energy_kwh') {
                $actual = ($aggregate['activities']['electricity_kwh']['amount'] ?? 0)
                    + ($aggregate['activities']['heat_kwh']['amount'] ?? 0)
                    + ($aggregate['activities']['gas_kwh']['amount'] ?? 0);
            } elseif ($target->metric === 'waste_kg') {
                $actual = $aggregate['activities']['waste_kg']['amount'] ?? 0;
            }

            return [
                'target' => $target,
                'expected' => $target->expectedFor((int) now()->format('Y')),
                'actual' => $actual,
            ];
        });

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($aggregate, $from, $to, $request);
        }
        // Vollaudit 2026-07 (N21): PDF über die 076-Report-Pipeline.
        if ($request->query('export') === 'pdf') {
            return $this->pdfDownload('sustainability.pdf.report', [
                'aggregate' => $aggregate,
                'factorSetNames' => $this->emissions->activeSetNames($orgId),
                'from' => $from,
                'to' => $to,
            ], sprintf('nachhaltigkeit_%s_%s.pdf', $from, $to), 'portrait', $request, 'sustainability', ['from' => $from, 'to' => $to]);
        }

        return view('sustainability.index', [
            'sites' => SustainabilitySite::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Kunden mit Kundengruppe (MVP-949) als Bezug der Aktivität.
            'groupedCustomers' => \App\Models\Customer\Customer::query()->whereHas('classifications', fn ($q) => $q->where('domain', \App\Enums\Classification\ClassificationDomain::CustomerGroup->value))->orderBy('name')->get(['id', 'name']),
            'from' => $from,
            'to' => $to,
            'aggregate' => $aggregate,
            'critical' => $critical,
            'openMeasures' => $openMeasures,
            'estimatedShare' => $estimatedShare,
            'targets' => $targets,
            'assessments' => SustainabilityAssessment::query()->orderByDesc('id')->limit(self::DASHBOARD_LIMIT)->get(),
            'assessmentCount' => SustainabilityAssessment::query()->count(),
            'measures' => SustainabilityMeasure::query()->with('responsible')->orderByDesc('id')->limit(self::DASHBOARD_LIMIT)->get(),
            'measureCount' => SustainabilityMeasure::query()->count(),
            'criteria' => SustainabilityCriterion::query()->orderBy('dimension')->get(),
            'factorSets' => SustainabilityFactorSet::query()->with('factors')
                ->where('active', true)
                ->where(fn($q) => $q->whereNull('organization_id')->orWhere('organization_id', $orgId))
                ->get(),
            'mappings' => SustainabilityFrameMapping::query()->where('active', true)->whereNull('organization_id')->orderBy('section_code')->get(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'canManage' => Auth::user()?->can(P::SustainabilityManage->value) || (Auth::user()?->isAdmin() ?? false),
        ]);
    }

    // ── Kriterienkatalog (MVP-224) ───────────────────────────────────────

    public function storeCriterion(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $data = $request->validate([
            'dimension' => ['required', 'in:environment,social,governance'],
            'label' => ['required', 'string', 'max:200'],
            'weight' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        SustainabilityCriterion::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
            'active' => true,
        ]);

        return back()->with('status', __('Kriterium angelegt.'));
    }

    // ── Aktivitätsdaten (MVP-227) ────────────────────────────────────────

    public function storeActivity(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        if ($request->filled('site_id')) {
            $request->merge(['site_id' => \App\Support\Sqid::decodeOrNumeric(SustainabilitySite::class, $request->string('site_id')->toString())]);
        }
        if ($request->filled('customer_id')) {
            $request->merge(['customer_id' => \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer\Customer::class, $request->string('customer_id')->toString())]);
        }
        $data = $request->validate([
            'site_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('sustainability_sites')],
            'customer_id' => ['nullable', 'integer', 'prohibits:site_id', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'activity_code' => ['required', 'in:' . implode(',', SustainabilityActivityRecord::ACTIVITY_CODES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'unit' => ['required', 'string', 'max:20'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'data_quality' => ['required', 'in:measured,calculated,estimated'],
            'subject_label' => ['nullable', 'string', 'max:200'],
            'source_note' => ['nullable', 'string', 'max:300'],
        ]);

        // Standort (MVP-929) als Bezug der Aktivität.
        $site = isset($data['site_id']) ? SustainabilitySite::query()->find((int) $data['site_id']) : null;
        // Kundenbezug (MVP-949) für den Vergleich nach Kundengruppe.
        $customer = isset($data['customer_id']) ? \App\Models\Customer\Customer::query()->find((int) $data['customer_id']) : null;
        unset($data['site_id'], $data['customer_id']);
        $subject = $site ?? $customer;
        if ($subject !== null) {
            $data += ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id];
            $data['subject_label'] = $subject->name;
        }

        SustainabilityActivityRecord::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Aktivitätsdatensatz erfasst.'));
    }

    // ── Faktorenbibliothek (MVP-228, Org-Override) ───────────────────────

    public function storeFactor(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $data = $request->validate([
            'activity_code' => ['required', 'in:' . implode(',', SustainabilityActivityRecord::ACTIVITY_CODES)],
            'label' => ['required', 'string', 'max:200'],
            'factor' => ['required', 'numeric', 'min:0', 'max:999999.999999'],
            'unit_code' => ['required', 'string', 'max:40'],
            'scope' => ['required', 'integer', 'in:1,2,3'],
            'valid_from' => ['required', 'date'],
            'source_note' => ['nullable', 'string', 'max:300'],
        ]);

        $orgId = $this->currentOrganization()->id;
        // Org-Override-Set (P1): je Org+Jahr genau eines, wird bei Bedarf angelegt.
        $set = SustainabilityFactorSet::query()->firstOrCreate([
            'organization_id' => $orgId,
            'year' => (int) now()->format('Y'),
            'name' => (string) __('Org-Override'),
        ], ['source' => (string) __('Eigene Faktoren'), 'region' => 'DE', 'active' => true]);

        $set->factors()->create([...$data, 'quality' => 'medium']);

        return back()->with('status', __('Faktor (Org-Override) angelegt — überschreibt das Standard-Set ab dem Gültigkeitsdatum.'));
    }

    // ── Bewertungen (MVP-225/226/230) ────────────────────────────────────

    public function storeAssessment(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $data = $request->validate([
            'subject_label' => ['required', 'string', 'max:200'],
        ]);

        try {
            $assessment = $this->assessments->createDraft(
                $this->currentOrganization()->id,
                null,
                null,
                $data['subject_label'],
                $this->actor(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('sustainability.assessments.show', $assessment)->with('status', __('Bewertungsentwurf angelegt.'));
    }

    /** Alle Bewertungen — das Dashboard zeigt nur die jüngsten. */
    public function assessmentIndex(Request $request): View {
        Gate::authorize('viewAny', SustainabilityAssessment::class);

        [$sort, $dir] = SortableQuery::resolve($request, self::ASSESSMENT_SORTS, 'created');

        return view('sustainability.assessments.index', [
            // Zweitschlüssel id: gleiche Werte (Status, Version) blättern sonst nicht stabil.
            'assessments' => SustainabilityAssessment::query()
                ->orderBy(self::ASSESSMENT_SORTS[$sort], $dir)
                ->orderBy('id', $dir)
                ->paginate(25)
                ->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function showAssessment(Request $request, SustainabilityAssessment $assessment): View {
        Gate::authorize('view', $assessment);
        $assessment->load(['items.criterion', 'assessor']);

        // Alternativenvergleich (MVP-230): zweite Bewertung nebenan.
        $compare = null;
        $compareSqid = (string) $request->query('vergleich', '');
        if ($compareSqid !== '') {
            $compareId = \App\Support\Sqid::decodeOrNumeric(SustainabilityAssessment::class, $compareSqid);
            $compare = $compareId !== null ? SustainabilityAssessment::query()->with('items.criterion')->find($compareId) : null;
        }

        return view('sustainability.assessment', [
            'assessment' => $assessment,
            'compare' => $compare,
            'others' => SustainabilityAssessment::query()->whereKeyNot($assessment->id)->orderByDesc('id')->limit(50)->get(['id', 'subject_label', 'version', 'status']),
            'canManage' => Gate::allows('manage', $assessment),
        ]);
    }

    public function scoreItem(Request $request, SustainabilityAssessment $assessment, \App\Models\Sustainability\SustainabilityAssessmentItem $item): RedirectResponse {
        Gate::authorize('update', $assessment);
        // Harter Guard AUCH für Admins (Policy-Bypass): finale Bewertungen
        // sind eingefroren — Änderungen nur über eine neue Version (P2).
        abort_if($assessment->isFinal(), 403);
        abort_unless($item->assessment_id === $assessment->id, 404);
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:5'],
            'data_quality' => ['required', 'in:measured,calculated,estimated'],
            'source_note' => ['nullable', 'string', 'max:300'],
            'justification' => ['nullable', 'string', 'max:1000'],
        ]);
        $item->update($data);

        return back()->with('status', __('Kriterium bewertet.'));
    }

    public function finalizeAssessment(SustainabilityAssessment $assessment): RedirectResponse {
        Gate::authorize('manage', $assessment);

        try {
            $this->assessments->finalize($assessment, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('status', __('Bewertung finalisiert und eingefroren.'));
    }

    public function newAssessmentVersion(SustainabilityAssessment $assessment): RedirectResponse {
        Gate::authorize('manage', $assessment);

        try {
            $next = $this->assessments->newVersion($assessment, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('sustainability.assessments.show', $next)->with('status', __('Version :v angelegt.', ['v' => $next->version]));
    }

    // ── Maßnahmen (MVP-229) ──────────────────────────────────────────────

    /** Alle Maßnahmen mit Statuspflege — ältere erreicht man nur hier. */
    public function measureIndex(Request $request): View {
        Gate::authorize('viewAny', SustainabilityAssessment::class);

        $status = (string) $request->query('status', '');
        $query = SustainabilityMeasure::query()->with('responsible');
        if ($status === 'open') {
            $query->whereIn('status', SustainabilityMeasureStatus::open());
        } elseif (($single = SustainabilityMeasureStatus::tryFrom($status)) !== null) {
            $query->where('status', $single);
        } else {
            $status = '';
        }
        [$sort, $dir] = SortableQuery::resolve($request, self::MEASURE_SORTS, 'created');

        return view('sustainability.measures.index', [
            'measures' => $query->orderBy(self::MEASURE_SORTS[$sort], $dir)->orderBy('id', $dir)->paginate(25)->withQueryString(),
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
            'canManage' => Gate::allows('create', SustainabilityAssessment::class),
        ]);
    }

    public function storeMeasure(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $request->merge(['responsible_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'expected_impact' => ['nullable', 'string', 'max:500'],
            'effort' => ['required', 'in:low,medium,high'],
            'cost_estimate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'due_on' => ['nullable', 'date'],
        ]);

        SustainabilityMeasure::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
            'status' => SustainabilityMeasureStatus::Proposed,
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Maßnahme erfasst.'));
    }

    public function updateMeasure(Request $request, SustainabilityMeasure $measure): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $data = $request->validate([
            'status' => ['required', Rule::enum(SustainabilityMeasureStatus::class)],
            'evidence_note' => ['nullable', 'string', 'max:1000'],
            'effectiveness' => ['nullable', 'in:effective,partly,ineffective'],
            'effectiveness_note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Die Statuspflege steht im Dashboard und in der Maßnahmenliste; die Liste behält Filter, Sortierung und Seite.
        $return = static fn (): RedirectResponse => $request->input('origin') === 'list'
            ? redirect()->toList('sustainability.measures.index')
            : back();

        // Wirksamkeitsprüfung erst nach Umsetzung (done).
        if (($data['effectiveness'] ?? null) !== null && $data['status'] !== SustainabilityMeasureStatus::Done->value) {
            return $return()->with('error', __('Wirksamkeit wird erst nach der Umsetzung geprüft.'));
        }

        $measure->update([
            ...$data,
            'reviewed_by' => ($data['effectiveness'] ?? null) !== null ? (int) Auth::id() : $measure->reviewed_by,
            'reviewed_at' => ($data['effectiveness'] ?? null) !== null ? now() : $measure->reviewed_at,
        ]);

        return $return()->with('status', __('Maßnahme aktualisiert.'));
    }

    // ── Ziele (MVP-231) ──────────────────────────────────────────────────

    public function storeTarget(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $data = $request->validate([
            'metric' => ['required', 'in:co2e_total,energy_kwh,waste_kg,repair_quota,sustainable_procurement_share,custom'],
            'label' => ['required', 'string', 'max:200'],
            'baseline_value' => ['required', 'numeric'],
            'baseline_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'target_value' => ['required', 'numeric'],
            'target_year' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:baseline_year'],
            'unit' => ['required', 'string', 'max:20'],
        ]);

        SustainabilityTarget::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
        ]);

        return back()->with('status', __('Ziel angelegt.'));
    }

    // ── Berichts-Snapshot (MVP-233) ──────────────────────────────────────

    public function storeSnapshot(Request $request): RedirectResponse {
        Gate::authorize('create', SustainabilityAssessment::class);
        $orgId = $this->currentOrganization()->id;
        $from = (string) $request->input('from', now()->startOfYear()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        $aggregate = $this->emissions->aggregate($orgId, $from, $to);
        SustainabilityReportSnapshot::query()->create([
            'organization_id' => $orgId,
            'period_start' => $from,
            'period_end' => $to,
            'data' => [
                ...$aggregate,
                'methodology' => [
                    'factor_sets' => $this->emissions->activeSetNames($orgId),
                    'note' => (string) __('Kennzahlen ohne Konformitäts- oder Klimaneutralitätsbehauptung; Schätzwerte sind ausgewiesen.'),
                ],
            ],
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Berichts-Snapshot (Managementbewertung) eingefroren.'));
    }

    /** @param array<string, mixed> $aggregate */
    private function exportCsv(array $aggregate, string $from, string $to, Request $request): Response {
        $rows = [['Bereich', 'Schlüssel', 'Menge', 'Einheit', 'CO2e kg', 'Faktorquelle']];
        foreach ($aggregate['activities'] as $code => $activity) {
            $rows[] = ['Aktivität', $code, NumberHelper::toUSFormat((float) $activity['amount'], 3), $activity['unit'], $activity['co2e_kg'] !== null ? NumberHelper::toUSFormat((float) $activity['co2e_kg'], 3) : 'FAKTOR FEHLT', $activity['factor_source'] ?? ''];
        }
        foreach ($aggregate['co2e_by_scope'] as $scope => $value) {
            $rows[] = ['Scope', (string) $scope, '', '', NumberHelper::toUSFormat((float) $value, 3), ''];
        }
        $rows[] = ['Gesamt', 'co2e_total', '', '', NumberHelper::toUSFormat((float) $aggregate['co2e_total_kg'], 3), ''];
        foreach ($aggregate['quality_share'] as $quality => $count) {
            $rows[] = ['Datenqualität', $quality, (string) $count, 'Datensätze', '', ''];
        }
        $rows[] = ['Methodik', 'Hinweis', '', '', '', 'Schätzwerte gekennzeichnet; keine Konformitätszusage (VSME-Vorbereitung).'];

        return $this->csvWithMetadata($rows, sprintf('sustainability_%s_%s.csv', $from, $to), 'sustainability', ['from' => $from, 'to' => $to], $request);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /** Fachbericht außerhalb der Auswertungen: Den Export deckt das Fachrecht der Seite (E28). */
    protected function exportNeedsReportPermission(): bool {
        return false;
    }
}
