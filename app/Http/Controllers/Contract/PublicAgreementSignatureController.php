<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicAgreementSignatureController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Contract;

use App\Enums\Contract\SignatureLinkPurpose;
use App\Http\Controllers\Article\ArticleExportController;
use App\Http\Controllers\Concerns\ChecksTenantPublicSurfaces;
use App\Http\Controllers\Controller;
use App\Models\Contract\{ContractSignatureLink, ContractSigningRevision};
use App\Models\Platform\Organization;
use App\Services\Contract\ContractSigningService;
use App\Support\{DocumentLocale, ErrorText};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\App;
use RuntimeException;
use Symfony\Component\HttpFoundation\{HeaderUtils, Response};

/**
 * Öffentliche Signatur- und Abrufseiten einer Kundenvereinbarung (Feature
 * 157, MVP-822). Ein Link berechtigt zu genau einer Fassung und Partei; nach
 * der Tokenauflösung wird der Mandantenkontext explizit gebunden. GET ändert
 * keinen Status (außer „geöffnet"), Seiten sind `no-store`/`noindex`.
 */
class PublicAgreementSignatureController extends Controller {
    use ChecksTenantPublicSurfaces;

    public function __construct(private readonly ContractSigningService $signing) {}

    public function show(string $token): View|Response {
        try {
            $link = $this->signing->resolveLink($token, SignatureLinkPurpose::Sign);
        } catch (RuntimeException $e) {
            return $this->error($e, 410);
        }
        $this->bind($link);
        $this->signing->markOpened($link);

        $revision = $link->revision()->with(['contract.customer', 'contract.organization', 'manifestItems', 'requests'])->firstOrFail();
        $signatureRequest = $link->request()->firstOrFail();
        if ($this->signing->verifyManifest($revision) !== []) {
            return $this->error(new RuntimeException((string) __('contract-signing.error.manifest_changed', ['files' => '—'])), 409);
        }

        return $this->page(view('public.agreement-sign', [
            'token' => $token,
            'link' => $link,
            'revision' => $revision,
            'contract' => $revision->contract,
            'signatureRequest' => $signatureRequest,
            'items' => $revision->manifestItems,
        ]));
    }

    public function sign(Request $request, string $token): RedirectResponse|Response {
        $data = ContractSigningController::validateSignature($request);
        try {
            $link = $this->signing->resolveLink($token, SignatureLinkPurpose::Sign);
            $this->bind($link);
            $this->signing->signViaLink($link, $data, $request);
        } catch (RuntimeException $e) {
            return $this->error($e, 410);
        }

        return redirect()->route('agreements.public-thanks')->with('agreement_thanks', 'signed');
    }

    public function upload(Request $request, string $token): RedirectResponse|Response {
        $data = ContractSigningController::validateUpload($request);
        /** @var UploadedFile $file */
        $file = $request->file('evidence_file');
        try {
            $link = $this->signing->resolveLink($token, SignatureLinkPurpose::Sign);
            $this->bind($link);
            $this->signing->submitUploadViaLink($link, $file, $data, $request);
        } catch (RuntimeException $e) {
            return $this->error($e, 410);
        }

        return redirect()->route('agreements.public-thanks')->with('agreement_thanks', 'uploaded');
    }

    public function thanks(): Response {
        return $this->page(view('public.agreement-thanks', ['outcome' => (string) session('agreement_thanks', 'signed')]));
    }

    /** Gebundene Datei des Manifests — für Signatur- und Abruflinks. */
    public function file(string $token, int $item): Response {
        $link = $this->resolveAny($token);
        if ($link instanceof Response) {
            return $link;
        }
        $this->bind($link);
        $revision = $link->revision()->firstOrFail();
        $entry = $this->signing->manifestItem($revision, $item);
        $bytes = $this->signing->readVersion($entry->documentVersion);
        if ((string) \CommonToolkit\Helper\Data\CryptoHelper::hash($bytes) !== $entry->sha256) {
            return $this->error(new RuntimeException((string) __('contract-signing.error.manifest_changed', ['files' => $entry->original_name])), 409);
        }

        return $this->noStore(response($bytes, 200, [
            'Content-Type' => $entry->documentVersion->mime ?? 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $entry->original_name),
        ]));
    }

    public function download(string $token): View|Response {
        try {
            $link = $this->signing->resolveLink($token, SignatureLinkPurpose::Download);
        } catch (RuntimeException $e) {
            return $this->error($e, 410);
        }
        $this->bind($link);
        $this->signing->markOpened($link);
        $revision = $link->revision()->with(['contract.customer', 'contract.organization', 'manifestItems', 'requests.evidences'])->firstOrFail();

        return $this->page(view('public.agreement-download', [
            'token' => $token,
            'link' => $link,
            'revision' => $revision,
            'contract' => $revision->contract,
            'items' => $revision->manifestItems,
        ]));
    }

    public function certificate(string $token): Response {
        [$link, $revision] = $this->downloadContext($token);
        if ($link instanceof Response) {
            return $link;
        }
        $revision->contract?->audit('contract.signing.certificateDownloaded', ['revision_id' => $revision->id, 'via' => 'link', 'link_id' => $link->id]);

        return $this->noStore(response($this->signing->certificatePdf($revision), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'abschlussnachweis.pdf'),
        ]));
    }

    public function package(string $token): Response {
        [$link, $revision] = $this->downloadContext($token);
        if ($link instanceof Response) {
            return $link;
        }
        $revision->contract?->audit('contract.signing.packageDownloaded', ['revision_id' => $revision->id, 'via' => 'link', 'link_id' => $link->id]);

        return $this->noStore(ArticleExportController::buildZipResponse($this->signing->packageFiles($revision), 'vereinbarung-fassung-' . $revision->revision_no . '.zip'));
    }

    // ── intern ─────────────────────────────────────────────────────────────

    /** @return array{0: ContractSignatureLink|Response, 1: ContractSigningRevision} */
    private function downloadContext(string $token): array {
        try {
            $link = $this->signing->resolveLink($token, SignatureLinkPurpose::Download);
        } catch (RuntimeException $e) {
            return [$this->error($e, 410), new ContractSigningRevision];
        }
        $this->bind($link);

        return [$link, $link->revision()->firstOrFail()];
    }

    private function resolveAny(string $token): ContractSignatureLink|Response {
        foreach ([SignatureLinkPurpose::Sign, SignatureLinkPurpose::Download] as $purpose) {
            try {
                return $this->signing->resolveLink($token, $purpose);
            } catch (RuntimeException) {
                continue;
            }
        }

        return $this->error(new RuntimeException((string) __('contract-signing.error.link_unknown')), 410);
    }

    /**
     * Mandantenkontext binden (Sperre gesperrter Mandanten, Anzeige-Zeitzone,
     * Locale des Kunden) — der Link selbst wurde ohne Scope aufgelöst.
     */
    private function bind(ContractSignatureLink $link): void {
        $org = $this->signing->organizationOf($link);
        abort_unless($org instanceof Organization, 404);
        $this->assertTenantPublicSurfacesAvailable($org);
        app()->instance('currentOrganization', $org);

        // Ab hier laufen die Relationen wieder unter dem Mandanten-Scope.
        App::setLocale(DocumentLocale::for($link->revision()->first()?->contract?->customer, $org));
    }

    private function error(RuntimeException $e, int $status): Response {
        return $this->noStore(response()->view('public.agreement-sign-error', ['message' => ErrorText::for($e)], $status));
    }

    private function page(View $view): Response {
        return $this->noStore(response($view->render()));
    }

    private function noStore(Response $response): Response {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
