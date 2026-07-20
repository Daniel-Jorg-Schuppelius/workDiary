<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Protocol\ProtocolItemPhotoPhase;
use App\Enums\User\Permission;
use App\Exceptions\{InvalidProtocolTransitionException, ProtocolValidationException};
use App\Http\Requests\Protocol\{AddProtocolItemRequest, FillProtocolItemRequest, IssueProtocolSignatureTokenRequest, StoreProtocolRequest, TransitionProtocolRequest, UpdateProtocolRequest, UploadProtocolItemPhotoRequest};
use App\Models\{Asset, Customer, DiaryEntry, Project, Protocol, ProtocolItem, ProtocolItemPhoto, User};
use App\Services\Protocol\{ProtocolItemPhotoService, ProtocolPdfRenderer, ProtocolService, ProtocolSignatureTokenService};
use App\Services\Weather\WeatherService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtocolController extends Controller {
    /**
     * Whitelist der erlaubten Subject-Typen für Protokolle (auch von
     * {@see StoreProtocolRequest} referenziert).
     *
     * @var array<string, class-string<Model>>
     */
    public const SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'project' => Project::class,
        'customer' => Customer::class,
        'asset' => Asset::class,
    ];

    public function __construct(
        private readonly ProtocolService $service,
    ) {}

    /**
     * Read-only-Detailseite (Rang 28): Trägerseite für Positionen, Signaturen,
     * Wetter-Nachweis, Anhänge, Verlauf und das Externe-Beteiligte-Panel.
     */
    public function show(Protocol $protocol): \Illuminate\View\View {
        Gate::authorize('view', $protocol);

        $protocol->load([
            'items.children',
            'items.photos.attachment',
            'signatures',
            // Externe Signatur-Links inkl. Widerruf (Vollaudit 2026-07, M6).
            'signatureTokens',
            'subject',
            'weatherSnapshot',
            'creator:id,name',
            'events.actor:id,name',
            'attachments',
            'tags',
        ]);

        return view('protocols.show', [
            'protocol' => $protocol,
        ]);
    }

    public function store(StoreProtocolRequest $request): RedirectResponse {
        Gate::authorize('create', Protocol::class);

        $data = $request->validated();

        $subjectClass = self::SUBJECT_MAP[$data['subject_kind']];
        /** @var Model|null $subject */
        $subject = $subjectClass::query()->find((int) $data['subject_id']);
        if ($subject === null) {
            abort(404);
        }

        /** @var User $creator */
        $creator = Auth::user();

        $protocol = $this->service->create($subject, $creator, $data);
        $this->syncTags($protocol, $request);

        return redirect()
            ->back()
            ->with('success', __('protocol.flash.created'))
            ->withFragment('protocol-' . $protocol->id);
    }

    public function update(UpdateProtocolRequest $request, Protocol $protocol): RedirectResponse {
        Gate::authorize('update', $protocol);

        $data = $request->validated();

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->service->update($protocol, $actor, $data);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        if ($request->has('tag_ids') || $request->has('new_tags')) {
            $this->syncTags($protocol, $request);
        }

        return redirect()->back()->with('success', __('protocol.flash.updated'));
    }

    public function destroy(Protocol $protocol): RedirectResponse {
        Gate::authorize('delete', $protocol);

        $protocol->delete();

        return redirect()->back()->with('success', __('protocol.flash.deleted'));
    }

    public function transition(TransitionProtocolRequest $request, Protocol $protocol, string $action): RedirectResponse {
        Gate::authorize($this->actionToAbility($action), $protocol);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            match ($action) {
                'requestReview' => $this->service->requestReview($protocol, $actor),
                'returnToDraft' => $this->service->returnToDraft(
                    $protocol,
                    $actor,
                    $request->validated()['reason'] ?? null,
                ),
                'sign' => $this->service->sign($protocol, $actor, $request->signaturePayload()),
                'archive' => $this->service->archive($protocol, $actor),
                'supersede' => $this->service->supersede(
                    $protocol,
                    $actor,
                    (string) $request->validated()['reason'],
                ),
                default => throw new InvalidArgumentException('Unbekannte Aktion: ' . $action),
            };
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['reason' => $e->getMessage()]);
        } catch (ProtocolValidationException $e) {
            return redirect()->back()->withErrors(['validation' => implode(' • ', $e->errors())]);
        }

        return redirect()
            ->back()
            ->with('success', __('protocol.flash.transition.' . $action))
            ->withFragment('protocol-' . $protocol->id);
    }

    public function addItem(AddProtocolItemRequest $request, Protocol $protocol): RedirectResponse {
        Gate::authorize('update', $protocol);

        $data = $request->validated();

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->service->addItem($protocol, $actor, $data);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('protocol.flash.item.added'));
    }

    public function fillItem(FillProtocolItemRequest $request, ProtocolItem $item): RedirectResponse {
        Gate::authorize('update', $item->protocol);

        $data = $request->validated();

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->service->fillItem($item, $actor, $data);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        } catch (ProtocolValidationException $e) {
            return redirect()->back()->withErrors(['value_json' => implode(' • ', $e->errors())]);
        }

        return redirect()->back()->with('success', __('protocol.flash.item.filled'));
    }

    public function destroyItem(ProtocolItem $item): RedirectResponse {
        Gate::authorize('update', $item->protocol);

        try {
            /** @var User $actor */
            $actor = Auth::user();
            $this->service->removeItem($item, $actor);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('protocol.flash.item.removed'));
    }

    public function uploadPhoto(
        UploadProtocolItemPhotoRequest $request,
        ProtocolItem $item,
        ProtocolItemPhotoService $photos,
    ): RedirectResponse {
        Gate::authorize('update', $item->protocol);
        /** @var User $u */
        $u = Auth::user();
        $allowGeo = $u->can(Permission::ProtocolItemPhotoViewGeo->value);

        $data = $request->validated();

        $phase = ProtocolItemPhotoPhase::from($data['phase']);

        try {
            $photos->upload(
                $item,
                $request->file('photo'),
                $phase,
                $u,
                [
                    'caption' => $data['caption'] ?? null,
                    'allow_geo' => (bool) ($data['allow_geo'] ?? false) && $allowGeo,
                ],
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['photo' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('protocol.flash.photo.uploaded'));
    }

    public function destroyPhoto(
        ProtocolItemPhoto $photo,
        ProtocolItemPhotoService $photos,
    ): RedirectResponse {
        $item = $photo->item;
        if ($item === null) {
            abort(404);
        }
        Gate::authorize('update', $item->protocol);
        /** @var User $u */
        $u = Auth::user();

        $photos->detach($photo, $u);

        return redirect()->back()->with('success', __('protocol.flash.photo.removed'));
    }

    /** Vollaudit 2026-07 (H7): Caption nachträglich pflegen (Service auditiert). */
    public function updatePhotoCaption(
        Request $request,
        ProtocolItemPhoto $photo,
        ProtocolItemPhotoService $photos,
    ): RedirectResponse {
        $item = $photo->item;
        if ($item === null) {
            abort(404);
        }
        Gate::authorize('update', $item->protocol);
        /** @var User $u */
        $u = Auth::user();

        $data = $request->validate(['caption' => ['nullable', 'string', 'max:180']]);
        $photos->updateCaption($photo, $data['caption'] ?? null, $u);

        return redirect()->back()->with('success', __('protocol.flash.photo.captionUpdated'));
    }

    /** Vollaudit 2026-07 (H7): Foto eine Position nach vorn (Service-reorder, auditiert). */
    public function promotePhoto(
        ProtocolItemPhoto $photo,
        ProtocolItemPhotoService $photos,
    ): RedirectResponse {
        $item = $photo->item;
        if ($item === null) {
            abort(404);
        }
        Gate::authorize('update', $item->protocol);
        /** @var User $u */
        $u = Auth::user();

        /** @var list<int> $ordered */
        $ordered = ProtocolItemPhoto::query()
            ->where('protocol_item_id', $item->id)
            ->where('phase', $photo->phase->value)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->values()
            ->all();
        $pos = array_search((int) $photo->id, $ordered, true);
        if (is_int($pos) && $pos > 0) {
            [$ordered[$pos - 1], $ordered[$pos]] = [$ordered[$pos], $ordered[$pos - 1]];
            $photos->reorder($item, $photo->phase, array_values($ordered), $u);
        }

        return redirect()->back()->with('success', __('protocol.flash.photo.reordered'));
    }

    public function issueSignatureToken(
        IssueProtocolSignatureTokenRequest $request,
        Protocol $protocol,
        ProtocolSignatureTokenService $tokens,
    ): RedirectResponse {
        Gate::authorize('sign', $protocol);
        /** @var User|null $u */
        $u = Auth::user();
        if (! $u || ! $u->can(Permission::ProtocolSignatureRequest->value)) {
            abort(403);
        }

        $data = $request->validated();

        /** @var User $actor */
        $actor = Auth::user();
        $result = $tokens->issue($protocol, $actor, $data);

        return redirect()->back()
            ->with('success', __('protocol.signature.tokenIssued'))
            ->with('protocol.signature.token_url', route('protocols.public-sign', ['token' => $result['token']]));
    }

    /** Widerruf eines externen Signatur-Links (Feature 012 MVP; Vollaudit 2026-07, M6). */
    public function revokeSignatureToken(
        Protocol $protocol,
        \App\Models\ProtocolSignatureToken $token,
        ProtocolSignatureTokenService $tokens,
    ): RedirectResponse {
        Gate::authorize('sign', $protocol);
        /** @var User|null $u */
        $u = Auth::user();
        if (! $u || ! $u->can(Permission::ProtocolSignatureRequest->value)) {
            abort(403);
        }
        abort_unless((int) $token->protocol_id === (int) $protocol->id, 404);

        try {
            $tokens->revoke($token, $u);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('protocol.signature.tokenRevoked'));
    }

    public function pdf(
        Protocol $protocol,
        ProtocolPdfRenderer $renderer,
    ): StreamedResponse {
        Gate::authorize('view', $protocol);
        /** @var User|null $u */
        $u = Auth::user();
        if (! $u || ! $u->can(Permission::ProtocolPdfDownload->value)) {
            abort(403);
        }

        /** @var User $actor */
        $actor = $u;
        $path = $this->service->renderPdfFor($protocol, $actor);
        $this->service->recordPdfDownload($protocol, $actor);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(ProtocolPdfRenderer::DISK);

        return $disk->download($path, sprintf('protokoll-%d-r%d.pdf', $protocol->id, $protocol->revision));
    }

    /**
     * Wetter-Messwert des Protokolltags anhängen (Feature 062, MVP-131):
     * Koordinaten aus dem Subjekt (Kunde/Projekt/Baustelle), unveränderlicher
     * Snapshot. Ausfall/kein Ort blockiert nicht — sichtbare Meldung.
     */
    public function attachWeather(Protocol $protocol, WeatherService $weather): RedirectResponse {
        Gate::authorize('update', $protocol);

        /** @var User $actor */
        $actor = Auth::user();
        $snapshot = $weather->snapshotForProtocol($protocol, $actor);

        return $snapshot !== null
            ? back()->with('success', __('weather.attach.success'))
            : back()->withErrors(['weather' => __('weather.attach.unavailable')]);
    }

    /**
     * Synchronisiert die Tags eines Protokolls aus den (optionalen)
     * Formularfeldern `tag_ids[]` (Sqid/ID bestehender Tags) und `new_tags`
     * (kommaseparierte neue Tag-Namen) — gleiche Mechanik wie bei Kunde/Asset.
     */
    private function syncTags(Protocol $protocol, Request $request): void {
        // Kanonische Tag-Normalisierung (Vollaudit 2026-07, M40): Sqid-Dekodierung
        // und new_tags-Zerlegung zentral in TagInput; Org-Prüfung in HasTags.
        $protocol->syncTagsFromInput(
            \App\Support\TagInput::ids($request->input('tag_ids', [])),
            \App\Support\TagInput::names($request->input('new_tags', '')),
        );
    }

    private function actionToAbility(string $action): string {
        return match ($action) {
            'requestReview', 'returnToDraft' => 'requestReview',
            'sign' => 'sign',
            'archive' => 'archive',
            'supersede' => 'supersede',
            default => 'update',
        };
    }
}
