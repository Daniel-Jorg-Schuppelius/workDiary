<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConformityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\NormConformityStatus;
use App\Http\Controllers\Controller;
use App\Models\{Document, User};
use App\Models\Isms\{IsmsNormStatus, IsmsRequirement, IsmsScope};
use App\Services\Isms\{ConformityService, ScopeService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Zertifizierungen (Feature 046, Inkrement B): Konformitätsstatus je
 * Geltungsbereich + Norm/Ausgabe mit Statuskette (nur erlaubte Übergänge,
 * `certified` NUR mit heute gültigem Zertifikat — zentral im
 * ConformityService erzwungen) und Zertifikatsregister je Statuszeile
 * (Modal „Zertifikat hinterlegen", Zertifikats-PDF aus dem
 * Dokumentenmodul). Fehlende Statuszeilen entstehen aus den
 * norm/edition-Paaren der Org-Anforderungen (ensure, idempotent);
 * zusätzliche Normen sind manuell anlegbar. Autorisierung über
 * IsmsNormStatusPolicy (isms.viewAny/view/manage).
 */
class ConformityController extends Controller {
    public function __construct(
        private readonly ConformityService $service,
        private readonly ScopeService $scopeService,
        private readonly SqidEncoder $sqids,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsNormStatus::class);

        $scopes = IsmsScope::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $scope = $this->resolveScope($request->query('scope'), $scopes);

        $statuses = $scope === null
            ? collect()
            : IsmsNormStatus::query()
                ->with(['certificates' => fn($query) => $query->orderByDesc('valid_until'), 'certificates.document'])
                ->where('isms_scope_id', $scope->id)
                ->get()
                ->sortBy(fn(IsmsNormStatus $s): string => $s->normLabel())
                ->values();

        // Norm/Ausgabe-Paare der Org-Anforderungen ohne Statuszeile im
        // gewählten Scope: „Statuszeilen anlegen" anbieten (idempotent).
        $known = $statuses->map(fn(IsmsNormStatus $s): string => $s->norm . '|' . $s->edition)->flip();
        $missingPairs = IsmsRequirement::query()
            ->select(['norm', 'edition'])
            ->distinct()
            ->get()
            ->filter(fn(IsmsRequirement $r): bool => ! isset($known[$r->norm . '|' . $r->edition]))
            ->map(fn(IsmsRequirement $r): string => $r->normLabel())
            ->sort()
            ->values();

        // Stichtags-Rekonstruktion (Nachtrag 046b): ?as_of=YYYY-MM-DD zeigt
        // den Bewertungsstand zu Datum T aus den append-only Snapshots.
        $reconstruction = null;
        $asOf = trim((string) $request->query('as_of', ''));
        if ($asOf !== '' && $scope !== null && strtotime($asOf) !== false) {
            $reconstruction = app(\App\Services\Isms\AssessmentSnapshotService::class)
                ->stateAt($scope, \Carbon\CarbonImmutable::parse($asOf)->endOfDay());
        }

        return view('isms.conformity.index', [
            'statuses' => $statuses,
            'scope' => $scope,
            'scopes' => $scopes,
            'missingPairs' => $missingPairs,
            'reconstruction' => $reconstruction,
            'canManage' => Gate::allows('create', IsmsNormStatus::class),
        ]);
    }

    /** Statuszeile manuell anlegen (zusätzliche Norm/Ausgabe) — Modal. */
    public function create(Request $request): View {
        Gate::authorize('create', IsmsNormStatus::class);

        return view('isms.conformity._form_dialog', [
            'scope' => $this->resolveScope($request->query('scope'), null),
            'scopes' => IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsNormStatus::class);

        $data = $request->validate([
            'scope' => ['required', 'string', 'max:64'],
            'norm' => ['required', 'string', 'max:64'],
            'edition' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $creator */
        $creator = Auth::user();

        // Fehlender/ungültiger Scope fällt auf den Default-Scope zurück
        // (wird bei Bedarf angelegt — analog Katalog-Import).
        $scope = $this->resolveScope($data['scope'], null)
            ?? $this->scopeService->ensureDefaultScope((int) $creator->organization_id);
        $this->service->create($creator, $scope, $data);

        return redirect()
            ->route('isms.conformity.index', ['scope' => $scope->sqid])
            ->with('success', __('isms.flash.norm_status_created'));
    }

    /**
     * Fehlende Statuszeilen für einen Geltungsbereich anlegen — je
     * norm/edition-Paar der Org-Anforderungen (idempotent).
     */
    public function ensure(IsmsScope $scope): RedirectResponse {
        Gate::authorize('create', IsmsNormStatus::class);

        $created = $this->service->ensureStatusesForScope($scope);

        return redirect()
            ->route('isms.conformity.index', ['scope' => $scope->sqid])
            ->with('success', __('isms.flash.norm_statuses_ensured', ['count' => $created]));
    }

    /**
     * Statuswechsel entlang der State-Machine — `certified` nur mit heute
     * gültigem Zertifikat (strikte 046-Regel, serverseitig im Service).
     */
    public function transition(Request $request, IsmsNormStatus $normStatus): RedirectResponse {
        Gate::authorize('transition', $normStatus);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(NormConformityStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transition($normStatus, NormConformityStatus::from($data['status']), $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.norm_status_transitioned'));
    }

    /** „Zertifikat hinterlegen"-Modal (046-Pflichtfelder + Dokument). */
    public function createCertificate(IsmsNormStatus $normStatus): View {
        Gate::authorize('addCertificate', $normStatus);

        return view('isms.conformity._certificate_dialog', [
            'status' => $normStatus->load('scope'),
            'documents' => Document::query()
                ->orderBy('title')
                ->get(['id', 'title']),
        ]);
    }

    public function storeCertificate(Request $request, IsmsNormStatus $normStatus): RedirectResponse {
        Gate::authorize('addCertificate', $normStatus);

        $data = $request->validate([
            'certified_organization' => ['required', 'string', 'max:180'],
            'scope_description' => ['required', 'string', 'max:10000'],
            'certification_body' => ['required', 'string', 'max:180'],
            'certificate_no' => ['required', 'string', 'max:120'],
            'issued_on' => ['required', 'date'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'surveillance_audit_1_on' => ['nullable', 'date'],
            'surveillance_audit_2_on' => ['nullable', 'date'],
            'document_id' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Sqid → ID (org-sicher erneut im Service über die org-gescopte
        // Document-Query aufgelöst).
        $data['document_id'] = isset($data['document_id']) && $data['document_id'] !== ''
            ? $this->sqids->decode(Document::class, (string) $data['document_id'])
            : null;

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->addCertificate($normStatus, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.certificate_added'));
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
}
