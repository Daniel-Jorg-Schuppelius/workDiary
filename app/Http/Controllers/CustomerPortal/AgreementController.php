<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Contract\{ContractKind, SigningRevisionStatus};
use App\Http\Controllers\Article\ArticleExportController;
use App\Http\Controllers\Controller;
use App\Models\Contract\ContractSigningRevision;
use App\Models\Platform\User;
use App\Services\Contract\ContractSigningService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\{HeaderUtils, Response};

/**
 * Portal-Sicht auf Kundenvereinbarungen (Feature 157, MVP-822): die eigenen
 * AVV-/NDA-Fassungen mit Stand; Dateien und Abschlussnachweis nur für
 * ausdrücklich freigegebene, vollständig unterzeichnete Fassungen. Harte
 * Scope-Grenze über Organisation + Kunde des Portalkontos.
 */
class AgreementController extends Controller {
    public function __construct(private readonly ContractSigningService $signing) {}

    public function index(): View {
        $revisions = $this->scope()
            ->whereIn('status', [SigningRevisionStatus::Ready->value, SigningRevisionStatus::PartiallySigned->value, SigningRevisionStatus::Signed->value, SigningRevisionStatus::Superseded->value])
            ->with(['contract', 'requests'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('customer.agreements.index', ['revisions' => $revisions]);
    }

    public function certificate(string $revision): Response {
        $model = $this->released($revision);
        $model->contract?->audit('contract.signing.certificateDownloaded', ['revision_id' => $model->id, 'via' => 'portal']);

        return response($this->signing->certificatePdf($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'abschlussnachweis.pdf'),
        ]);
    }

    public function package(string $revision): Response {
        $model = $this->released($revision);
        $model->contract?->audit('contract.signing.packageDownloaded', ['revision_id' => $model->id, 'via' => 'portal']);

        return ArticleExportController::buildZipResponse($this->signing->packageFiles($model), 'vereinbarung-fassung-' . $model->revision_no . '.zip');
    }

    public function file(string $revision, int $item): Response {
        $model = $this->released($revision);
        $entry = $this->signing->manifestItem($model, $item);

        return response($this->signing->readVersion($entry->documentVersion), 200, [
            'Content-Type' => $entry->documentVersion->mime ?? 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $entry->original_name),
        ]);
    }

    /** Freigegebene, unterzeichnete Fassung des eigenen Kunden — sonst 404 (kein Leak). */
    private function released(string $sqid): ContractSigningRevision {
        $id = Sqid::decodeOrAbort(ContractSigningRevision::class, $sqid);
        /** @var ContractSigningRevision $model */
        $model = $this->scope()
            ->whereKey($id)
            ->whereNotNull('customer_visible_at')
            ->whereIn('status', [SigningRevisionStatus::Signed->value, SigningRevisionStatus::Superseded->value])
            ->with('contract')
            ->firstOrFail();

        return $model;
    }

    /** @return Builder<ContractSigningRevision> */
    private function scope(): Builder {
        $user = $this->portalUser();

        return ContractSigningRevision::query()
            ->withoutGlobalScopes()
            ->where('organization_id', (int) $user->organization_id)
            ->whereHas('contract', fn (Builder $q) => $q
                ->withoutGlobalScopes()
                ->where('organization_id', (int) $user->organization_id)
                ->where('customer_id', (int) $user->customer_id)
                ->whereIn('kind', array_map(static fn (ContractKind $k): string => $k->value, ContractKind::signingKinds())));
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
