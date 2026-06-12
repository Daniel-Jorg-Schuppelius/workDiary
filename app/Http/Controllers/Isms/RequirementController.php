<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\{ControlImplementationStatus, RequirementSource};
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsRequirement, IsmsScope};
use App\Models\User;
use App\Services\Isms\{NormProfileRegistry, RegisterExportService, RequirementService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anforderungen + SoA je Geltungsbereich (Feature 044/046): Listenseite
 * mit Scope-Auswahl (Query-Param scope={sqid}, Default = Default-Scope)
 * und Norm-Filter (norm+edition aus den vorhandenen Requirements);
 * Modal-Bearbeitung der SoA-Aussage (Statement); Normkatalog-Import über
 * die Normprofil-Registry (idempotent); fehlende Statements je Scope
 * nachziehbar (ensureStatements); eigene Anforderungen als Modal-CRUD.
 * Autorisierung über IsmsRequirementPolicy (isms.viewAny/view/manage).
 */
class RequirementController extends Controller {
    /** Trennzeichen des kombinierten Norm-Filterwerts "norm|edition". */
    private const NORM_FILTER_SEPARATOR = '|';

    public function __construct(
        private readonly RequirementService $service,
        private readonly NormProfileRegistry $registry,
        private readonly SqidEncoder $sqids,
        private readonly RegisterExportService $exports,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRequirement::class);

        $scopes = IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $scope = $this->resolveScope($request->query('scope'), $scopes);
        $normOptions = $this->normOptions();

        $filters = [
            'source' => (string) $request->query('source', 'all'),
            'applicable' => (string) $request->query('applicable', 'all'),
            'implementation_status' => (string) $request->query('implementation_status', 'all'),
            'norm' => $this->validNormFilter((string) $request->query('norm', 'all'), $normOptions),
        ];

        $statements = $scope === null
            ? collect()
            : IsmsApplicabilityStatement::query()
                ->where('isms_scope_id', $scope->id)
                ->get()
                ->keyBy('isms_requirement_id');

        // Katalog- + eigene Anforderungen: bewusst ohne Pagination,
        // natürliche Ref-Sortierung (A.5.2 vor A.5.10) im PHP-Nachgang.
        $requirements = IsmsRequirement::query()
            ->withCount('controls')
            ->get()
            ->sort(fn(IsmsRequirement $a, IsmsRequirement $b): int => strcmp($a->norm, $b->norm)
                ?: strnatcmp($a->ref_no, $b->ref_no))
            ->values();

        if ($filters['norm'] !== 'all') {
            [$norm, $edition] = $this->parseNormFilter($filters['norm']);
            $requirements = $requirements
                ->filter(fn(IsmsRequirement $r): bool => $r->norm === $norm && $r->edition === $edition)
                ->values();
        }
        if (RequirementSource::tryFrom($filters['source']) !== null) {
            $requirements = $requirements->where('source', RequirementSource::from($filters['source']))->values();
        }
        if (in_array($filters['applicable'], ['yes', 'no'], true)) {
            $wanted = $filters['applicable'] === 'yes';
            $requirements = $requirements
                ->filter(fn(IsmsRequirement $r): bool => ($statements[$r->id]->applicable ?? true) === $wanted)
                ->values();
        }
        if (ControlImplementationStatus::tryFrom($filters['implementation_status']) !== null) {
            $wanted = ControlImplementationStatus::from($filters['implementation_status']);
            $requirements = $requirements
                ->filter(fn(IsmsRequirement $r): bool => ($statements[$r->id]->implementation_status ?? null) === $wanted)
                ->values();
        }

        $hasActiveFilters = $filters['source'] !== 'all'
            || $filters['applicable'] !== 'all'
            || $filters['implementation_status'] !== 'all'
            || $filters['norm'] !== 'all';

        // Scope ohne Statements zu den angezeigten Anforderungen:
        // „SoA-Aussagen anlegen" anbieten (ensureStatements, idempotent).
        $missingStatements = $scope !== null
            && $requirements->contains(fn(IsmsRequirement $r): bool => ! isset($statements[$r->id]));

        return view('isms.requirements.index', [
            'requirements' => $requirements,
            'statements' => $statements,
            'scope' => $scope,
            'scopes' => $scopes,
            'filters' => $filters,
            'normOptions' => $normOptions,
            'profiles' => $this->registry->all(),
            'hasActiveFilters' => $hasActiveFilters,
            'missingStatements' => $missingStatements,
            'catalogLoaded' => IsmsRequirement::query()->where('source', RequirementSource::Catalog->value)->exists(),
            'canManage' => Gate::allows('create', IsmsRequirement::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsRequirement::class);

        return view('isms.requirements._form_dialog', ['requirement' => null]);
    }

    /**
     * Direkt-Export der Anforderungen/SoA-Aussagen des gewählten
     * Geltungsbereichs (?scope={sqid}&format=json|csv) — gleiches Gate
     * wie die Listenseite; meta-Block/Kopf trägt den Datenstand.
     */
    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', IsmsRequirement::class);

        $format = (string) $request->query('format', 'json');
        abort_unless(in_array($format, RegisterExportService::FORMATS, true), 404);

        $scope = $this->resolveScope($request->query('scope'), null);
        abort_if($scope === null, 404);

        /** @var User $actor */
        $actor = Auth::user();
        $register = $this->exports->soaRegister($scope);

        $content = $format === 'csv'
            ? $this->exports->toCsv(RegisterExportService::REGISTER_SOA, $actor, $scope, $register)
            : $this->exports->toJson(RegisterExportService::REGISTER_SOA, $actor, $scope, $register);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $this->exports->filename(RegisterExportService::REGISTER_SOA, $format), [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8',
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsRequirement::class);

        /** @var User $creator */
        $creator = Auth::user();
        $data = $this->validateRequirement($request, $creator, null);

        $this->service->create($creator, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.requirement_created'));
    }

    public function edit(IsmsRequirement $requirement): View {
        Gate::authorize('update', $requirement);

        return view('isms.requirements._form_dialog', ['requirement' => $requirement]);
    }

    public function update(Request $request, IsmsRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $requirement);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $this->validateRequirement($request, $actor, $requirement);

        $this->service->update($requirement, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.requirement_updated'));
    }

    public function destroy(IsmsRequirement $requirement): RedirectResponse {
        Gate::authorize('delete', $requirement);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($requirement, $actor);

        return redirect()
            ->route('isms.requirements.index')
            ->with('success', __('isms.flash.requirement_deleted'));
    }

    /**
     * Normkatalog eines Registry-Profils laden (idempotent, nur Ref-Nr. +
     * Kurztitel) — Statements entstehen im aktuell gewählten Scope.
     */
    public function import(Request $request): RedirectResponse {
        Gate::authorize('import', IsmsRequirement::class);

        $data = $request->validate([
            'profile' => ['required', 'string', Rule::in($this->registry->keys())],
            'scope' => ['nullable', 'string', 'max:64'],
        ]);

        $scope = $this->resolveScope($data['scope'] ?? null, null);

        /** @var User $actor */
        $actor = Auth::user();
        $created = $this->service->importCatalog($actor, (string) $data['profile'], $scope);

        $profile = $this->registry->get((string) $data['profile']);

        return redirect()
            ->route('isms.requirements.index', array_filter(['scope' => $scope?->sqid]))
            ->with('success', __('isms.flash.catalog_imported', ['label' => $profile['label'], 'count' => $created]));
    }

    /**
     * Fehlende SoA-Aussagen für einen Geltungsbereich anlegen (idempotent)
     * — optional begrenzt auf eine Norm (kombinierter Filterwert
     * "norm|edition" wie auf der Listenseite).
     */
    public function ensureStatements(Request $request, IsmsScope $scope): RedirectResponse {
        Gate::authorize('updateStatement', IsmsRequirement::class);

        $normFilter = $this->validNormFilter((string) $request->input('norm', 'all'), $this->normOptions());
        [$norm, $edition] = $normFilter === 'all' ? [null, null] : $this->parseNormFilter($normFilter);

        $created = $this->service->ensureStatementsForScope($scope, $norm, $edition);

        return redirect()
            ->route('isms.requirements.index', array_filter(['scope' => $scope->sqid, 'norm' => $normFilter !== 'all' ? $normFilter : null]))
            ->with('success', __('isms.flash.statements_ensured', ['count' => $created]));
    }

    /** SoA-Aussage (Statement) bearbeiten — Modal. */
    public function editStatement(IsmsApplicabilityStatement $statement): View {
        Gate::authorize('updateStatement', IsmsRequirement::class);

        return view('isms.requirements._statement_dialog', [
            'statement' => $statement->load('requirement'),
        ]);
    }

    public function updateStatement(Request $request, IsmsApplicabilityStatement $statement): RedirectResponse {
        Gate::authorize('updateStatement', IsmsRequirement::class);

        $data = $request->validate([
            'applicable' => ['nullable', 'boolean'],
            // SoA-Regel: Begründung Pflicht bei Nicht-Anwendbarkeit —
            // zusätzlich zentral im RequirementService durchgesetzt.
            'justification' => ['nullable', 'string', 'max:5000', 'required_if:applicable,0'],
            'implementation_status' => ['required', 'string', Rule::enum(ControlImplementationStatus::class)],
            'evidence_note' => ['nullable', 'string', 'max:10000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateStatement($statement, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.statement_updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequirement(Request $request, User $actor, ?IsmsRequirement $requirement): array {
        $isCatalog = $requirement !== null && $requirement->source === RequirementSource::Catalog;

        return $request->validate([
            // Norm/Edition/Ref-Nr. sind bei Katalog-Anforderungen Referenz
            // (unveränderlich, erzwungen im RequirementService).
            'norm' => [$isCatalog ? 'nullable' : 'required', 'string', 'max:64'],
            'edition' => ['nullable', 'string', 'max:16'],
            'ref_no' => [
                $isCatalog ? 'nullable' : 'required', 'string', 'max:24',
                Rule::unique('isms_requirements', 'ref_no')
                    ->where('organization_id', $actor->organization_id)
                    ->where('norm', trim((string) $request->input('norm', $requirement?->norm)))
                    ->where('edition', trim((string) $request->input('edition', $requirement?->edition)) ?: '-')
                    ->ignore($requirement?->id),
            ],
            'title' => ['required', 'string', 'min:3', 'max:180'],
        ]);
    }

    /**
     * Löst den Scope-Query-/Formularparameter (Sqid) auf — ungültige,
     * fremde (Org-Scope!) oder fehlende Werte fallen auf den Default-Scope
     * zurück.
     *
     * @param  Collection<int, IsmsScope>|null  $scopes  bereits geladene Scopes (optional)
     */
    private function resolveScope(mixed $sqid, ?Collection $scopes): ?IsmsScope {
        if (is_string($sqid) && $sqid !== '') {
            $id = $this->sqids->decode(IsmsScope::class, $sqid);
            $scope = $id === null
                ? null
                : ($scopes !== null
                    ? $scopes->firstWhere('id', $id)
                    : IsmsScope::query()->whereKey($id)->first());

            if ($scope !== null) {
                return $scope;
            }
        }

        return $scopes !== null
            ? $scopes->firstWhere('is_default', true)
            : IsmsScope::query()->where('is_default', true)->first();
    }

    /**
     * Norm-Filter-Optionen aus den vorhandenen Requirements der Org:
     * value = "norm|edition", label = normLabel().
     *
     * @return Collection<int, array{value: non-falsy-string, label: string}>
     */
    private function normOptions(): Collection {
        return IsmsRequirement::query()
            ->select(['norm', 'edition'])
            ->distinct()
            ->get()
            ->map(fn(IsmsRequirement $r): array => [
                'value' => $r->norm . self::NORM_FILTER_SEPARATOR . $r->edition,
                'label' => $r->normLabel(),
            ])
            ->unique('value')
            ->sortBy('label')
            ->values();
    }

    /**
     * @param  Collection<int, array{value: non-falsy-string, label: string}>  $normOptions
     */
    private function validNormFilter(string $value, Collection $normOptions): string {
        return $normOptions->contains(fn(array $option): bool => $option['value'] === $value) ? $value : 'all';
    }

    /**
     * Zerlegt den kombinierten Norm-Filterwert "norm|edition".
     *
     * @return array{0: string, 1: string}
     */
    private function parseNormFilter(string $value): array {
        $pos = strrpos($value, self::NORM_FILTER_SEPARATOR);

        return $pos === false
            ? [$value, '-']
            : [substr($value, 0, $pos), substr($value, $pos + 1)];
    }
}
