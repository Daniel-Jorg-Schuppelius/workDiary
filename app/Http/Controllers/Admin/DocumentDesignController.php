<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentDesignController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentDesign\{LetterheadAssetStatus, LetterheadPageRole, RenderDocumentKind, TableStylePreset};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\DocumentDesign\{DocumentRenderProfile, DocumentRenderProfileVersion, LetterheadAsset};
use App\Models\{Organization, User};
use App\Services\DocumentDesign\{LetterheadAssetService, RenderPreflightService, RenderProfileService, SampleDocumentService};
use App\Services\SqidEncoder;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Admin-Verwaltung „Dokumentdesign" (Feature 076): Firmenbogen-Uploads,
 * Renderprofile mit versioniertem Millimeter-Editor, Blockdeklarationen,
 * Tabellenstil, Preflight, Test-PDF und Aktivierung. Verwaltung erfordert
 * Org-Admin bzw. `documentDesign.manage`; Fachadmins mit
 * `documentDesign.assign` dürfen Zuweisung und Vorschau, aber keine
 * organisationsweiten Assets ersetzen.
 */
class DocumentDesignController extends Controller {
    public function __construct(
        private readonly LetterheadAssetService $assets,
        private readonly RenderProfileService $profiles,
        private readonly RenderPreflightService $preflight,
        private readonly SampleDocumentService $samples,
    ) {}

    public function index(): View {
        $user = $this->assignUser();
        $organization = $this->organization($user);

        return view('admin.document-design.index', [
            'profiles' => DocumentRenderProfile::query()
                ->where('organization_id', $organization->id)
                ->with('activeVersion')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'assets' => LetterheadAsset::query()
                ->where('organization_id', $organization->id)
                ->where('status', '!=', LetterheadAssetStatus::Archived)
                ->orderByDesc('created_at')
                ->get(),
            'kinds' => RenderDocumentKind::cases(),
            'canManage' => $this->canManage($user),
        ]);
    }

    /** Upload-Dialog (modal-first, MVP-296). */
    public function createAsset(): View {
        $this->manageUser();

        return view('admin.document-design._asset_form_dialog');
    }

    /** Profil-Dialog (modal-first). */
    public function createProfile(): View {
        $this->manageUser();

        return view('admin.document-design._profile_form_dialog', [
            'kinds' => RenderDocumentKind::cases(),
        ]);
    }

    /** Firmenbogen hochladen (modal-first, MVP-296). */
    public function storeAsset(Request $request): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'page_role' => ['required', 'in:first,following'],
            'file' => ['required', 'file', 'max:' . (int) config('document_design.limits.max_kb'), 'mimes:pdf,jpg,jpeg,png'],
        ]);

        try {
            $asset = $this->assets->store(
                $organization,
                $request->file('file'),
                LetterheadPageRole::from($data['page_role']),
                (string) $data['name'],
                $user,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('admin.document-design.index')->with(
            $asset->isReady() ? 'success' : 'error',
            $asset->isReady()
                ? __('Firmenbogen hochgeladen und geprüft.')
                : __('Firmenbogen hochgeladen — Prüfung erforderlich: :notes', ['notes' => implode(' ', $asset->review_notes ?? [])]),
        );
    }

    /** Normalisierte Vorschau eines Assets (nur eigene Organisation). */
    public function assetPreview(string $sqid): Response {
        $user = $this->assignUser();
        $organization = $this->organization($user);
        $asset = $this->asset($organization, $sqid);

        abort_if($asset->normalized_path === null, 404);
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->normalized_path), 404);

        return response((string) $disk->get($asset->normalized_path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function archiveAsset(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);
        $asset = $this->asset($organization, $sqid);

        $inUse = DocumentRenderProfileVersion::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', DocumentRenderProfileVersion::STATUS_SUPERSEDED)
            ->where(fn($q) => $q->where('first_asset_id', $asset->id)->orWhere('following_asset_id', $asset->id))
            ->exists();
        if ($inUse) {
            return back()->with('error', __('Der Firmenbogen wird von einem aktiven Profil oder Entwurf verwendet.'));
        }

        $this->assets->archive($asset);

        return back()->with('success', __('Firmenbogen archiviert.'));
    }

    /** Profil anlegen (Version 1 = Entwurf mit Standardlayout). */
    public function storeProfile(Request $request): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'document_kinds' => ['nullable', 'array'],
            'document_kinds.*' => ['string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $profile = $this->profiles->createProfile(
            $organization,
            (string) $data['name'],
            (array) ($data['document_kinds'] ?? []),
            (bool) ($data['is_default'] ?? false),
            $user,
        );

        return redirect()->route('admin.document-design.editor', $profile->sqid)
            ->with('success', __('Profil angelegt — Entwurf kann jetzt gestaltet werden.'));
    }

    /** Großflächiger Seiteneditor (begründete Spezialseite, MVP-302). */
    public function editor(string $sqid): View {
        $user = $this->assignUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $draft = $profile->versions()->where('status', DocumentRenderProfileVersion::STATUS_DRAFT)->first();
        $version = $draft ?? $profile->activeVersion;
        abort_if($version === null, 404);

        return view('admin.document-design.editor', [
            'profile' => $profile,
            'version' => $version,
            'isDraft' => $version->isDraft(),
            'assetsFirst' => $this->readyAssets($organization, LetterheadPageRole::First),
            'assetsFollowing' => $this->readyAssets($organization, LetterheadPageRole::Following),
            'kinds' => RenderDocumentKind::cases(),
            'presets' => TableStylePreset::cases(),
            'preflight' => $this->preflight->check($version, $profile->document_kinds ?? [])->toArray(),
            'versions' => $profile->versions()->orderByDesc('version')->get(),
            'canManage' => $this->canManage($user),
        ]);
    }

    /** Entwurf speichern (Layout, Blöcke, Tabellenstil, Assets). */
    public function updateDraft(Request $request, string $sqid): JsonResponse|RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $version = $profile->versions()
            ->where('status', DocumentRenderProfileVersion::STATUS_DRAFT)
            ->firstOrFail();

        $data = $request->validate([
            'layout' => ['nullable', 'array'],
            'block_rules' => ['nullable', 'array'],
            'table_style' => ['nullable', 'array'],
            'first_asset' => ['nullable', 'string'],
            'following_asset' => ['nullable', 'string'],
        ]);

        $payload = array_filter([
            'layout' => $data['layout'] ?? null,
            'block_rules' => $data['block_rules'] ?? null,
            'table_style' => $data['table_style'] ?? null,
        ], fn($v) => $v !== null);
        foreach ([['first_asset', 'first_asset_id'], ['following_asset', 'following_asset_id']] as [$input, $column]) {
            if ($request->exists($input)) {
                $sqidValue = $data[$input] ?? null;
                $payload[$column] = $sqidValue === null || $sqidValue === ''
                    ? null
                    : app(SqidEncoder::class)->decode(LetterheadAsset::class, (string) $sqidValue);
            }
        }

        try {
            $version = $this->profiles->updateDraft($version, $payload, $user);
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $result = $this->preflight->check($version, $profile->document_kinds ?? []);
        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'preflight' => $result->toArray()]);
        }

        return back()->with('success', __('Entwurf gespeichert.'));
    }

    /** Neue Entwurfsversion aus bestehendem Stand (auch Rollback, MVP-302). */
    public function newDraft(Request $request, string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $sourceSqid = (string) $request->validate(['source' => ['required', 'string']])['source'];
        $sourceId = app(SqidEncoder::class)->decode(DocumentRenderProfileVersion::class, $sourceSqid);
        $source = $sourceId === null ? null : DocumentRenderProfileVersion::query()
            ->where('organization_id', $organization->id)
            ->where('document_render_profile_id', $profile->id)
            ->find($sourceId);
        abort_unless($source instanceof DocumentRenderProfileVersion, 404);

        try {
            $this->profiles->newDraftFrom($source, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.document-design.editor', $profile->sqid)
            ->with('success', __('Neuer Entwurf auf Basis von Version :v angelegt.', ['v' => $source->version]));
    }

    /** Aktivierung nur mit fehlerfreiem Preflight (MVP-300). */
    public function activate(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $version = $profile->versions()
            ->where('status', DocumentRenderProfileVersion::STATUS_DRAFT)
            ->firstOrFail();

        $result = $this->profiles->activate($version, $user);
        if (! $result->ok()) {
            return back()->with('error', __('Aktivierung blockiert — der Preflight meldet :n Fehler.', ['n' => count($result->errors)]));
        }

        return back()->with('success', __('Version :v aktiviert.', ['v' => $version->version]));
    }

    /** Zuweisung/Standard (auch für Fachadmins mit documentDesign.assign). */
    public function assign(Request $request, string $sqid): RedirectResponse {
        $user = $this->assignUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $data = $request->validate([
            'document_kinds' => ['nullable', 'array'],
            'document_kinds.*' => ['string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $this->profiles->assignKinds($profile, (array) ($data['document_kinds'] ?? []));
        if ((bool) ($data['is_default'] ?? false)) {
            $this->profiles->setDefault($profile);
        }

        return back()->with('success', __('Zuweisung gespeichert.'));
    }

    public function archiveProfile(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $this->profiles->archive($profile);

        return redirect()->route('admin.document-design.index')->with('success', __('Profil archiviert.'));
    }

    /** Test-PDF je Dokumentart aus dem aktuellen Stand (Entwurf bevorzugt). */
    public function testPdf(Request $request, string $sqid): Response {
        $user = $this->assignUser();
        $organization = $this->organization($user);
        $profile = $this->profile($organization, $sqid);

        $kind = RenderDocumentKind::tryFrom((string) $request->query('kind')) ?? RenderDocumentKind::Invoice;
        $version = $profile->versions()->where('status', DocumentRenderProfileVersion::STATUS_DRAFT)->first()
            ?? $profile->activeVersion;
        abort_if($version === null, 404);

        $renderer = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class);
        $pdf = $this->samples->pdf($organization, $kind, $renderer->payloadFromVersion($version));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="testdokument-' . $kind->value . '.pdf"',
        ]);
    }

    private function canManage(User $user): bool {
        return $user->isAdmin() || $user->can(Permission::DocumentDesignManage->value);
    }

    private function manageUser(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function assignUser(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->canManage($user) || $user->can(Permission::DocumentDesignAssign->value), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $user): Organization {
        $org = $user->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }

    private function profile(Organization $organization, string $sqid): DocumentRenderProfile {
        $id = app(SqidEncoder::class)->decode(DocumentRenderProfile::class, $sqid);
        $profile = $id === null ? null : DocumentRenderProfile::query()
            ->where('organization_id', $organization->id)
            ->find($id);
        abort_unless($profile instanceof DocumentRenderProfile, 404);

        return $profile;
    }

    private function asset(Organization $organization, string $sqid): LetterheadAsset {
        $id = app(SqidEncoder::class)->decode(LetterheadAsset::class, $sqid);
        $asset = $id === null ? null : LetterheadAsset::query()
            ->where('organization_id', $organization->id)
            ->find($id);
        abort_unless($asset instanceof LetterheadAsset, 404);

        return $asset;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, LetterheadAsset> */
    private function readyAssets(Organization $organization, LetterheadPageRole $role) {
        return LetterheadAsset::query()
            ->where('organization_id', $organization->id)
            ->where('page_role', $role)
            ->where('status', LetterheadAssetStatus::Ready)
            ->orderBy('name')
            ->get();
    }
}
