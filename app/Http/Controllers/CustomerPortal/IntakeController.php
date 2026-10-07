<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Customer\IntakeKind;
use App\Http\Controllers\Concerns\{ValidatesIntakeSubmission, ValidatesUploadedFiles};
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Attachments\Attachment;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\User;
use App\Services\Customer\Intake\{CustomerIntakeHandoverService, CustomerIntakeQuoteService, CustomerIntakeService, CustomerIntakeStages, CustomerIntakeUploadChannels, IntakeTemplates};
use App\Services\CustomerPortal\PortalVisibility;
use App\Support\Sqid;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{Auth, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * „Anfragen und Aufträge" im Kundenportal (Feature 162, MVP-1074/1075):
 * Leistung anfragen (Druck/IT), Dateien an freigegebene Eingänge nachreichen,
 * Rückfragen beantworten, Angebot entscheiden. Jeder Zugriff prüft
 * Organisation und Kunde; Ziel-IDs aus dem Request ersetzen diese Prüfung nie.
 */
class IntakeController extends Controller {
    use ValidatesIntakeSubmission;
    use ValidatesUploadedFiles;

    public function __construct(
        private readonly CustomerIntakeService $intakes,
        private readonly CustomerIntakeQuoteService $quotes,
        private readonly CustomerIntakeStages $stages,
        private readonly CustomerIntakeHandoverService $handover,
        private readonly IntakeTemplates $templates,
        private readonly PortalVisibility $visibility,
        private readonly CustomerIntakeUploadChannels $channels,
    ) {}

    public function index(): View {
        $user = $this->portalUser();

        return view('customer.intakes.index', [
            'intakes' => CustomerIntake::query()->ofPortalUser($user)->with('quote')->orderByDesc('created_at')->paginate(25),
            'stages' => $this->stages,
            'hasUploadTargets' => $this->uploadTargets($user)->isNotEmpty(),
        ]);
    }

    public function create(Request $request): View {
        $user = $this->portalUser();
        $kind = IntakeKind::tryFrom((string) $request->query('kind', '')) ?? abort(404);

        return view('customer.intakes.create', [
            'kind' => $kind,
            'schema' => $this->templates->schema($kind),
            'assets' => $kind === IntakeKind::It ? $this->portalAssets($user) : collect(),
            'submissionKey' => (string) Str::uuid(),
            'action' => route('customer.intakes.store'),
            'catalogItem' => null,
            'catalogSchema' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse {
        $user = $this->portalUser();
        $kind = IntakeKind::tryFrom((string) $request->input('kind', '')) ?? abort(404);

        [$data, $form] = $this->validatedIntake($request, $kind);
        $purpose = $kind->uploadPurpose();
        $files = $this->validatedUploads($request, $purpose->maxFiles(), $purpose, 'uploads');
        $asset = $kind === IntakeKind::It ? $this->resolveAsset($user, (string) $request->input('asset', '')) : null;

        $intake = $this->intakes->submit($user, $data, $form, $files, asset: $asset);

        return $this->intakeResponse($request, route('customer.intakes.show', $intake), (string) __('customer_intake.flash.submitted', ['number' => $intake->number]));
    }

    public function show(CustomerIntake $intake): View {
        $this->assertOwned($intake);
        $intake->load(['quote.items', 'messages.author:id,name,customer_id', 'asset:id,name', 'requestItem:id,name']);

        $quote = $intake->quote;
        $adapter = $this->handover->targetFor($intake->kind);
        $target = $intake->target_id !== null ? $intake->target()->withoutGlobalScopes()->first() : null;

        return view('customer.intakes.show', [
            'intake' => $intake,
            'stage' => $this->stages->for($intake),
            'messages' => $intake->messages->filter(fn ($message): bool => $message->kind->isCustomerVisible())->values(),
            'files' => $intake->attachments()->where('customer_visible', true)->orderBy('created_at')->get(),
            'quote' => $quote !== null && $this->quotes->visibleToCustomer($quote) ? $quote : null,
            'quoteDecidable' => $quote !== null && $this->quotes->decidable($quote),
            'target' => $target,
            'targetPanel' => $target !== null ? $adapter?->portalPanelView() : null,
            'uploadChannel' => $intake->acceptsCustomerFiles() ? $this->channels->channelFor((int) $intake->organization_id) : null,
            'uploadLink' => $this->channels->activeLink($intake),
        ]);
    }

    /** Upload-Link öffnen (MVP-1078) — nur am eigenen Eingang, der Dateien annimmt. */
    public function openUploadLink(CustomerIntake $intake): RedirectResponse {
        $user = $this->portalUser();
        $this->assertOwned($intake);
        $this->channels->open($intake, $user);

        return redirect()->route('customer.intakes.show', $intake)->with('status', __('customer_intake.cloud.flash.opened'));
    }

    /** Hochgeladenes sofort übernehmen statt auf den nächsten Lauf zu warten. */
    public function syncUploadLink(CustomerIntake $intake): RedirectResponse {
        $user = $this->portalUser();
        $this->assertOwned($intake);
        $link = $this->channels->activeLink($intake) ?? abort(404);
        $result = $this->channels->sync($link, $user);

        return redirect()->route('customer.intakes.show', $intake)->with('status', __('customer_intake.cloud.flash.synced', ['imported' => $result['imported'], 'rejected' => $result['rejected']]));
    }

    /** Dateien nachreichen: Auswahl unter den eigenen, dafür freigegebenen Eingängen. */
    public function uploadForm(): View {
        $user = $this->portalUser();
        $targets = $this->uploadTargets($user);
        abort_if($targets->isEmpty(), 404);

        return view('customer.intakes.upload', ['targets' => $targets]);
    }

    public function uploadStore(Request $request): RedirectResponse|JsonResponse {
        $user = $this->portalUser();
        $request->validate(['intake' => ['required', 'string', 'max:64']]);
        $intake = $this->uploadTargets($user)->first(fn (CustomerIntake $candidate): bool => $candidate->sqid === (string) $request->input('intake'))
            ?? abort(404);

        return $this->addFiles($request, $intake, $user);
    }

    public function filesStore(Request $request, CustomerIntake $intake): RedirectResponse|JsonResponse {
        $user = $this->portalUser();
        $this->assertOwned($intake);

        return $this->addFiles($request, $intake, $user);
    }

    public function reply(Request $request, CustomerIntake $intake): RedirectResponse {
        $user = $this->portalUser();
        $this->assertOwned($intake);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $purpose = $intake->kind->uploadPurpose();
        $files = $this->validatedUploads($request, $purpose->maxFiles(), $purpose, 'uploads');
        $this->intakes->reply($intake, $user, (string) $data['body'], $files);

        return redirect()->route('customer.intakes.show', $intake)->with('status', __('customer_intake.flash.replied'));
    }

    public function withdraw(CustomerIntake $intake): RedirectResponse {
        $user = $this->portalUser();
        $this->assertOwned($intake);

        $this->intakes->withdraw($intake, $user);

        return redirect()->route('customer.intakes.show', $intake)->with('status', __('customer_intake.flash.withdrawn'));
    }

    public function decideQuote(Request $request, CustomerIntake $intake): RedirectResponse {
        $user = $this->portalUser();
        $this->assertOwned($intake);

        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->quotes->decide($intake, $user, $data['decision'] === 'accept', array_values((array) ($data['item_ids'] ?? [])), $data['reason'] ?? null);

        return redirect()->route('customer.intakes.show', $intake)->with('status', __('customer_intake.flash.quote_decided'));
    }

    /**
     * Sicherer Download: eigener Eingang, Anhang gehört dazu und ist
     * kundensichtbar — sonst 404. Pfade nur aus der DB.
     */
    public function download(CustomerIntake $intake, Attachment $attachment): BinaryFileResponse {
        $this->assertOwned($intake);
        abort_unless(
            $attachment->customer_visible
            && $attachment->attachable_type === $intake->getMorphClass()
            && (int) $attachment->attachable_id === (int) $intake->getKey(),
            404,
        );

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    private function addFiles(Request $request, CustomerIntake $intake, User $user): RedirectResponse|JsonResponse {
        $purpose = $intake->kind->uploadPurpose();
        $files = $this->validatedUploads($request, $purpose->maxFiles(), $purpose, 'uploads');
        $this->intakes->addCustomerFiles($intake, $user, $files);

        return $this->intakeResponse($request, route('customer.intakes.show', $intake), (string) __('customer_intake.flash.files_added', ['count' => count($files)]));
    }

    /** @return Collection<int, CustomerIntake> */
    private function uploadTargets(User $user): Collection {
        return CustomerIntake::query()->ofPortalUser($user)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (CustomerIntake $intake): bool => $intake->acceptsCustomerFiles())
            ->values();
    }

    /** @return Collection<int, Asset> */
    private function portalAssets(User $user): Collection {
        return $user->customer !== null ? $this->visibility->assetsFor($user->customer) : collect();
    }

    private function resolveAsset(User $user, string $sqid): ?Asset {
        if ($sqid === '') {
            return null;
        }
        $id = Sqid::decode(Asset::class, $sqid);

        return $this->portalAssets($user)->first(fn (Asset $asset): bool => (int) $asset->id === $id) ?? abort(422);
    }

    private function assertOwned(CustomerIntake $intake): void {
        $user = $this->portalUser();
        abort_unless(
            (int) $intake->organization_id === (int) $user->organization_id
            && (int) $intake->customer_id === (int) $user->customer_id,
            404,
        );
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 404);

        return $user;
    }
}
