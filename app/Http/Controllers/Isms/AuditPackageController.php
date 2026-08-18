<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditPackageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsAuditPackage, IsmsAuditPackageToken, IsmsScope};
use App\Models\User;
use App\Services\Isms\{AuditPackageService, ScopeService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auditpakete (Feature 046, Inkrement E / 044 „Auditbereitschaft"):
 * stichtagsbezogene, integritätsgeschützte JSON-Exportpakete je
 * Geltungsbereich (Modal-Anlage, Finalisieren friert ein,
 * Integritätsprüfung gegen file_hash) plus zeitlich begrenzte
 * Prüfer-Download-Links (Klartext-Token wird nach Erstellung genau
 * EINMAL als Flash angezeigt; Widerruf jederzeit). Autorisierung über
 * IsmsAuditPackagePolicy (isms.viewAny/view/manage); der öffentliche
 * Prüfer-Download läuft separat über PublicAuditPackageController.
 */
class AuditPackageController extends Controller {
    public function __construct(
        private readonly AuditPackageService $service,
        private readonly ScopeService $scopeService,
        private readonly SqidEncoder $sqids,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', IsmsAuditPackage::class);

        $packages = IsmsAuditPackage::query()
            ->with(['scope', 'finalizedBy:id,name', 'tokens' => fn($query) => $query->orderByDesc('id')])
            ->orderByDesc('package_no')
            ->get();

        return view('isms.packages.index', [
            'packages' => $packages,
            'canManage' => Gate::allows('create', IsmsAuditPackage::class),
        ]);
    }

    /** „Paket anlegen"-Modal (Scope, Stichtag, optionaler Norm-Filter). */
    public function create(): View {
        Gate::authorize('create', IsmsAuditPackage::class);

        return view('isms.packages._form_dialog', [
            'scopes' => IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsAuditPackage::class);

        $data = $request->validate([
            'scope' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:180'],
            'as_of_date' => ['required', 'date'],
            'norm' => ['nullable', 'string', 'max:64'],
            'edition' => ['nullable', 'string', 'max:16'],
        ]);

        /** @var User $creator */
        $creator = Auth::user();

        // Fehlender/ungültiger Scope fällt auf den Default-Scope zurück
        // (wird bei Bedarf angelegt — Muster ConformityController).
        $scope = $this->resolveScope($data['scope'])
            ?? $this->scopeService->ensureDefaultScope((int) $creator->organization_id);

        $this->service->create($creator, $scope, $data);

        return redirect()
            ->route('isms.packages.index')
            ->with('success', __('isms.flash.package_created'));
    }

    /** Finalisieren: Snapshot + SHA-256, danach unveränderlich. */
    public function finalize(IsmsAuditPackage $package): RedirectResponse {
        Gate::authorize('finalize', $package);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->finalize($package, $actor);

        return redirect()
            ->route('isms.packages.index')
            ->with('success', __('isms.flash.package_finalized'));
    }

    /** Integritätsprüfung eines einzelnen Pakets (Button, Flash-Ergebnis). */
    public function verify(IsmsAuditPackage $package): RedirectResponse {
        Gate::authorize('verify', $package);

        if ($this->service->verify($package)) {
            return redirect()
                ->route('isms.packages.index')
                ->with('success', __('isms.flash.package_verified_ok', ['no' => $package->displayNo()]));
        }

        return redirect()
            ->route('isms.packages.index')
            ->withErrors(['file_hash' => __('isms.flash.package_verified_mismatch', ['no' => $package->displayNo()])]);
    }

    /** Interner Download der Paketdatei (Gate-geprüft, pfadsicher). */
    public function download(IsmsAuditPackage $package): StreamedResponse {
        Gate::authorize('download', $package);

        $path = (string) $package->file_path;
        // Pfad-Härtung analog FinanceTransferController::download(): nur
        // Dateien aus dem Auditpaket-Verzeichnis, keine Traversal-Segmente.
        abort_unless($path !== '' && str_starts_with($path, AuditPackageService::BASE_PATH . '/'), 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk(AuditPackageService::DISK);
        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_if($stream === null, 404);

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($path), ['Content-Type' => 'application/json']);
    }

    /** „Prüfer-Link erstellen"-Modal (Label + Gültigkeit in Tagen). */
    public function createToken(IsmsAuditPackage $package): View {
        Gate::authorize('manageTokens', $package);

        return view('isms.packages._token_dialog', [
            'package' => $package->load('scope'),
        ]);
    }

    /**
     * Erstellt den Prüfer-Link; der vollständige Link (Klartext-Token)
     * wird genau EINMAL als Flash angezeigt — gespeichert ist nur der Hash.
     */
    public function storeToken(Request $request, IsmsAuditPackage $package): RedirectResponse {
        Gate::authorize('manageTokens', $package);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'days' => ['required', 'integer', 'min:' . AuditPackageService::MIN_TOKEN_DAYS, 'max:' . AuditPackageService::MAX_TOKEN_DAYS],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $issued = $this->service->createToken($package, $actor, $data['label'], (int) $data['days']);

        return redirect()
            ->route('isms.packages.index')
            ->with('success', __('isms.flash.package_token_created', ['label' => $issued['model']->label]))
            // Der ausgehändigte Link zeigt die Webansicht (Feature 046,
            // Live-Prüferzugang) - der Datei-Download ist dort verlinkt.
            ->with('isms_package_token_url', route('audit-packages.public-view', ['token' => $issued['token']]));
    }

    /** Widerruft einen Prüfer-Link (org-sicher über das Paket aufgelöst). */
    public function revokeToken(IsmsAuditPackageToken $token): RedirectResponse {
        // Mandantengrenze: das Token-Modell ist transitiv gescoped — die
        // org-gescopte Paket-Query sieht fremde Pakete nicht (404).
        $package = IsmsAuditPackage::query()
            ->whereKey($token->isms_audit_package_id)
            ->firstOrFail();

        Gate::authorize('manageTokens', $package);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->revokeToken($token, $actor);

        return redirect()
            ->route('isms.packages.index')
            ->with('success', __('isms.flash.package_token_revoked'));
    }

    /**
     * Löst den Scope-Formularparameter (Sqid) org-sicher auf — die
     * org-gescopte Scope-Query sieht fremde Scopes nicht.
     */
    private function resolveScope(string $sqid): ?IsmsScope {
        $id = $this->sqids->decode(IsmsScope::class, $sqid);

        return $id === null ? null : IsmsScope::query()->whereKey($id)->first();
    }
}
