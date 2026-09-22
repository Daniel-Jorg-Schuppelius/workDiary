<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSigningController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Contract;

use App\Enums\Contract\{ContractKind, SignatureMethod, SignatureParty};
use App\Http\Controllers\{ArticleExportController, Controller};
use App\Models\Contract\{Contract, ContractSignatureEvidence, ContractSignatureLink, ContractSignatureRequest, ContractSigningRevision};
use App\Models\{DocumentVersion, User};
use App\Services\Contract\ContractSigningService;
use App\Support\{ErrorText, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\{HeaderUtils, Response};

/**
 * Unterzeichnung von Kundenvereinbarungen im angemeldeten Bereich (Feature
 * 157, MVP-822): Fassungen, Links, Gegenzeichnung, Nachweise, Prüfung,
 * Abschlussnachweis und Paket. Jede Aktion prüft die Ability am Vertrag —
 * `signing` (Anforderung/Gegenzeichnung/Ablösung) bzw. `review` (Prüfung).
 */
class ContractSigningController extends Controller {
    public function __construct(private readonly ContractSigningService $signing) {}

    public function create(Contract $contract): View {
        Gate::authorize('signing', $contract);

        return view('contracts.signing._revision_form_dialog', $this->formData($contract, null));
    }

    public function store(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('signing', $contract);

        try {
            $this->signing->createRevision($contract, $this->actor($request), $this->validatedRevision($request, $contract));
        } catch (RuntimeException $e) {
            return back()->withErrors(['revision' => ErrorText::for($e)]);
        }

        return redirect()->route('contracts.show', $contract)->with('status', __('contract-signing.flash.revision_created'));
    }

    public function edit(ContractSigningRevision $revision): View {
        $contract = $this->contractOf($revision);
        Gate::authorize('signing', $contract);

        return view('contracts.signing._revision_form_dialog', $this->formData($contract, $revision->load(['manifestItems', 'requests'])));
    }

    public function update(Request $request, ContractSigningRevision $revision): RedirectResponse {
        $contract = $this->contractOf($revision);
        Gate::authorize('signing', $contract);

        try {
            $this->signing->updateRevision($revision, $this->actor($request), $this->validatedRevision($request, $contract));
        } catch (RuntimeException $e) {
            return back()->withErrors(['revision' => ErrorText::for($e)]);
        }

        return redirect()->route('contracts.show', $contract)->with('status', __('contract-signing.flash.revision_updated'));
    }

    public function prepare(Request $request, ContractSigningRevision $revision): RedirectResponse {
        Gate::authorize('signing', $this->contractOf($revision));

        return $this->act(fn () => $this->signing->prepare($revision, $this->actor($request)), __('contract-signing.flash.prepared'));
    }

    public function withdraw(Request $request, ContractSigningRevision $revision): RedirectResponse {
        Gate::authorize('signing', $this->contractOf($revision));
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->act(fn () => $this->signing->withdraw($revision, $this->actor($request), $data['reason']), __('contract-signing.flash.withdrawn'));
    }

    public function supersede(Request $request, ContractSigningRevision $revision): RedirectResponse {
        $contract = $this->contractOf($revision);
        Gate::authorize('signing', $contract);
        $data = $request->validate([
            'predecessor_id' => ['required', 'string'],
            'effective_on' => ['required', 'date'],
        ]);
        $predecessor = $contract->signingRevisions()->whereKey(Sqid::decodeOrAbort(ContractSigningRevision::class, $data['predecessor_id']))->firstOrFail();

        return $this->act(
            fn () => $this->signing->supersede($revision, $predecessor, $this->actor($request), CarbonImmutable::parse($data['effective_on'])),
            __('contract-signing.flash.superseded'),
        );
    }

    public function releaseToPortal(Request $request, ContractSigningRevision $revision): RedirectResponse {
        Gate::authorize('signing', $this->contractOf($revision));

        return $this->act(fn () => $this->signing->releaseToCustomer($revision, $this->actor($request)), __('contract-signing.flash.portal_released'));
    }

    public function revokeFromPortal(Request $request, ContractSigningRevision $revision): RedirectResponse {
        Gate::authorize('signing', $this->contractOf($revision));

        return $this->act(fn () => $this->signing->revokeFromCustomer($revision, $this->actor($request)), __('contract-signing.flash.portal_revoked'));
    }

    /** Abruflink (Ergebnis) erzeugen — mit Empfänger senden, sonst einmalig anzeigen. */
    public function issueDownloadLink(Request $request, ContractSigningRevision $revision): RedirectResponse {
        Gate::authorize('signing', $this->contractOf($revision));
        $data = $request->validate(['signer_email' => ['nullable', 'email', 'max:180']]);

        try {
            if (filled($data['signer_email'] ?? null)) {
                $this->signing->sendDownloadLink($revision, $this->actor($request), (string) $data['signer_email']);

                return back()->with('status', __('contract-signing.flash.link_sent', ['email' => $data['signer_email']]));
            }
            $issued = $this->signing->issueDownloadLink($revision, $this->actor($request));
        } catch (RuntimeException $e) {
            return back()->withErrors(['revision' => ErrorText::for($e)]);
        }

        return back()
            ->with('status', __('contract-signing.flash.link_issued'))
            ->with('signing_link', route('agreements.public-download', ['token' => $issued['token']]));
    }

    public function certificate(ContractSigningRevision $revision): Response {
        $contract = $this->contractOf($revision);
        Gate::authorize('view', $contract);

        try {
            $pdf = $this->signing->certificatePdf($revision);
        } catch (RuntimeException $e) {
            abort(409, ErrorText::for($e));
        }
        $contract->audit('contract.signing.certificateDownloaded', ['revision_id' => $revision->id]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $this->fileStem($revision) . '-abschlussnachweis.pdf'),
        ]);
    }

    public function package(ContractSigningRevision $revision): Response {
        $contract = $this->contractOf($revision);
        Gate::authorize('view', $contract);

        try {
            $files = $this->signing->packageFiles($revision);
        } catch (RuntimeException $e) {
            abort(409, ErrorText::for($e));
        }
        $contract->audit('contract.signing.packageDownloaded', ['revision_id' => $revision->id]);

        return ArticleExportController::buildZipResponse($files, $this->fileStem($revision) . '.zip');
    }

    /** Gebundene Dokumentversion des Manifests — exakt die eingefrorene, nie „aktuell". */
    public function file(ContractSigningRevision $revision, int $item): Response {
        Gate::authorize('view', $this->contractOf($revision));
        $entry = $this->signing->manifestItem($revision, $item);

        return response($this->signing->readVersion($entry->documentVersion), 200, [
            'Content-Type' => $entry->documentVersion->mime ?? 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $entry->original_name),
        ]);
    }

    // ── Anforderungen ──────────────────────────────────────────────────────

    public function issueLink(Request $request, ContractSignatureRequest $signatureRequest): RedirectResponse {
        Gate::authorize('signing', $this->contractOfRequest($signatureRequest));

        try {
            $issued = $this->signing->issueSignLink($signatureRequest, $this->actor($request));
        } catch (RuntimeException $e) {
            return back()->withErrors(['revision' => ErrorText::for($e)]);
        }

        return back()
            ->with('status', __('contract-signing.flash.link_issued'))
            ->with('signing_link', route('agreements.public-sign', ['token' => $issued['token']]));
    }

    public function sendLink(Request $request, ContractSignatureRequest $signatureRequest): RedirectResponse {
        Gate::authorize('signing', $this->contractOfRequest($signatureRequest));

        return $this->act(
            fn () => $this->signing->sendSignLink($signatureRequest, $this->actor($request)),
            __('contract-signing.flash.link_sent', ['email' => (string) $signatureRequest->signer_email]),
        );
    }

    public function revokeLink(Request $request, ContractSignatureLink $link): RedirectResponse {
        Gate::authorize('signing', $this->contractOf($link->revision()->firstOrFail()));

        return $this->act(fn () => $this->signing->revokeLink($link, $this->actor($request)), __('contract-signing.flash.link_revoked'));
    }

    public function countersignDialog(ContractSignatureRequest $signatureRequest): View {
        $contract = $this->contractOfRequest($signatureRequest);
        Gate::authorize('signing', $contract);

        return view('contracts.signing._countersign_dialog', [
            'contract' => $contract,
            'signatureRequest' => $signatureRequest->load('revision'),
        ]);
    }

    public function countersign(Request $request, ContractSignatureRequest $signatureRequest): RedirectResponse {
        $contract = $this->contractOfRequest($signatureRequest);
        Gate::authorize('signing', $contract);
        $data = self::validateSignature($request);

        return $this->act(
            fn () => $this->signing->countersign($signatureRequest, $this->actor($request), $data, $request),
            __('contract-signing.flash.countersigned'),
            route('contracts.show', $contract),
        );
    }

    public function uploadDialog(ContractSignatureRequest $signatureRequest): View {
        $contract = $this->contractOfRequest($signatureRequest);
        Gate::authorize('signing', $contract);

        return view('contracts.signing._upload_dialog', [
            'contract' => $contract,
            'signatureRequest' => $signatureRequest->load('revision'),
        ]);
    }

    public function upload(Request $request, ContractSignatureRequest $signatureRequest): RedirectResponse {
        $contract = $this->contractOfRequest($signatureRequest);
        Gate::authorize('signing', $contract);
        $data = self::validateUpload($request);
        /** @var UploadedFile $file */
        $file = $request->file('evidence_file');

        return $this->act(
            fn () => $this->signing->recordUploadInternally($signatureRequest, $this->actor($request), $file, $data, $request),
            __('contract-signing.flash.evidence_recorded'),
            route('contracts.show', $contract),
        );
    }

    public function review(Request $request, ContractSignatureEvidence $evidence): RedirectResponse {
        Gate::authorize('review', $this->contractOf($evidence->revision()->firstOrFail()));
        $data = $request->validate([
            'review_decision' => ['required', Rule::in(['accept', 'reject'])],
            'review_note' => [Rule::requiredIf($request->input('review_decision') === 'reject'), 'nullable', 'string', 'max:1000'],
        ]);
        $accept = $data['review_decision'] === 'accept';

        return $this->act(
            fn () => $this->signing->reviewEvidence($evidence, $this->actor($request), $accept, $data['review_note'] ?? null),
            $accept ? __('contract-signing.flash.evidence_accepted') : __('contract-signing.flash.evidence_rejected'),
        );
    }

    public function evidenceFile(ContractSignatureEvidence $evidence): Response {
        Gate::authorize('view', $this->contractOf($evidence->revision()->firstOrFail()));
        abort_unless($evidence->hasFile() && Storage::disk((string) $evidence->disk)->exists((string) $evidence->path), 404);

        $ext = $evidence->mime === 'application/pdf' ? 'pdf' : 'png';

        return response((string) Storage::disk((string) $evidence->disk)->get((string) $evidence->path), 200, [
            'Content-Type' => (string) $evidence->mime,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'nachweis-' . $evidence->party->value . '-' . $evidence->id . '.' . $ext),
        ]);
    }

    // ── Validierung (geteilt mit dem öffentlichen Weg) ─────────────────────

    /** @return array{signer_name: string, signer_function: ?string, signature_method: string, signature: ?string, declaration_accepted: bool, authority_confirmed: bool} */
    public static function validateSignature(Request $request): array {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'signer_function' => ['nullable', 'string', 'max:120'],
            'signature_method' => ['required', Rule::in([SignatureMethod::Drawn->value, SignatureMethod::Typed->value])],
            'signature' => [Rule::requiredIf($request->input('signature_method') === SignatureMethod::Drawn->value), 'nullable', 'string', 'max:1400000'],
            'typed_name' => [Rule::requiredIf($request->input('signature_method') === SignatureMethod::Typed->value), 'nullable', 'string', 'max:120'],
            'declaration_accepted' => ['accepted'],
            'authority_confirmed' => ['accepted'],
        ]);

        return [
            'signer_name' => (string) $data['signer_name'],
            'signer_function' => $data['signer_function'] ?? null,
            'signature_method' => (string) $data['signature_method'],
            'signature' => $data['signature'] ?? null,
            'declaration_accepted' => true,
            'authority_confirmed' => true,
        ];
    }

    /** @return array{signer_name: string, stated_signed_on: ?string} */
    public static function validateUpload(Request $request): array {
        $data = $request->validate([
            'evidence_file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'stated_signed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        return [
            'signer_name' => (string) $data['signer_name'],
            'stated_signed_on' => filled($data['stated_signed_on'] ?? null) ? CarbonImmutable::parse((string) $data['stated_signed_on'])->toDateString() : null,
        ];
    }

    // ── intern ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validatedRevision(Request $request, Contract $contract): array {
        $data = $request->validate([
            'contract_version_id' => ['required', 'string'],
            'attachment_version_ids' => ['nullable', 'array', 'max:20'],
            'attachment_version_ids.*' => ['string'],
            'controller_party' => [Rule::requiredIf($contract->kind === ContractKind::DataProcessing), 'nullable', Rule::enum(SignatureParty::class)],
            'declaration_text' => ['required', 'string', 'min:10', 'max:4000'],
            'review_on' => ['nullable', 'date'],
            'customer_signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_signer_function' => ['nullable', 'string', 'max:120'],
            'customer_signer_email' => ['nullable', 'email', 'max:180'],
            'organization_required' => ['sometimes', 'boolean'],
            'organization_signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'organization_signer_function' => ['nullable', 'string', 'max:120'],
            'organization_signer_email' => ['nullable', 'email', 'max:180'],
            'waiver_reason' => [Rule::requiredIf(! $request->boolean('organization_required', true)), 'nullable', 'string', 'max:500'],
        ]);

        return [
            'contract_version_id' => Sqid::decodeOrAbort(DocumentVersion::class, $data['contract_version_id'], 422),
            'attachment_version_ids' => array_map(
                static fn (string $id): int => Sqid::decodeOrAbort(DocumentVersion::class, $id, 422),
                array_values((array) ($data['attachment_version_ids'] ?? [])),
            ),
            'controller_party' => $contract->kind === ContractKind::DataProcessing ? ($data['controller_party'] ?? null) : null,
            'declaration_text' => (string) $data['declaration_text'],
            'review_on' => filled($data['review_on'] ?? null) ? CarbonImmutable::parse((string) $data['review_on'])->toDateString() : null,
            'customer' => [
                'signer_name' => $data['customer_signer_name'],
                'signer_function' => $data['customer_signer_function'] ?? null,
                'signer_email' => $data['customer_signer_email'] ?? null,
            ],
            'organization' => [
                'required' => $request->boolean('organization_required', true),
                'signer_name' => $data['organization_signer_name'],
                'signer_function' => $data['organization_signer_function'] ?? null,
                'signer_email' => $data['organization_signer_email'] ?? null,
                'waiver_reason' => $data['waiver_reason'] ?? null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formData(Contract $contract, ?ContractSigningRevision $revision): array {
        $contract->loadMissing(['customer', 'organization']);
        $versions = DocumentVersion::query()
            ->whereHas('document', fn ($q) => $q
                ->where('documentable_type', $contract->getMorphClass())
                ->where('documentable_id', $contract->getKey())
                ->where('organization_id', $contract->organization_id))
            ->where('mime', 'application/pdf')
            ->with('document:id,title')
            ->orderByDesc('id')
            ->get();

        return [
            'contract' => $contract,
            'revision' => $revision,
            'versions' => $versions,
            'defaultDeclaration' => (string) __('contract-signing.declaration.default', [
                'kind' => $contract->kind->label(),
                'organization' => $contract->organization->name ?? '',
            ]),
            'defaultOrganizationSigner' => $contract->responsible->name ?? '',
        ];
    }

    /** @param callable():mixed $action */
    private function act(callable $action, string $success, ?string $redirect = null): RedirectResponse {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['revision' => ErrorText::for($e)]);
        }

        return ($redirect !== null ? redirect()->to($redirect) : back())->with('status', $success);
    }

    private function contractOf(ContractSigningRevision $revision): Contract {
        return $revision->contract()->firstOrFail();
    }

    private function contractOfRequest(ContractSignatureRequest $signatureRequest): Contract {
        return $this->contractOf($signatureRequest->revision()->firstOrFail());
    }

    private function actor(Request $request): User {
        /** @var User $user */
        $user = $request->user() ?? abort(401);

        return $user;
    }

    private function fileStem(ContractSigningRevision $revision): string {
        $contract = $revision->contract;

        return \CommonToolkit\Helper\FileSystem\File::sanitizeDisplayName(($contract->number ?? 'vertrag') . '-fassung-' . $revision->revision_no);
    }
}
