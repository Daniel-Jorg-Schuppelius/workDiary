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
use App\Enums\Protocol\ProtocolItemType;
use App\Enums\Protocol\ProtocolSignatureMethod;
use App\Enums\Protocol\ProtocolSignatureRole;
use App\Enums\Protocol\ProtocolType;
use App\Enums\Protocol\ProtocolVisibility;
use App\Enums\User\Permission;
use App\Exceptions\InvalidProtocolTransitionException;
use App\Exceptions\ProtocolValidationException;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\Project;
use App\Models\Protocol;
use App\Models\ProtocolItem;
use App\Models\ProtocolItemPhoto;
use App\Models\User;
use App\Services\Protocol\ProtocolItemPhotoService;
use App\Services\Protocol\ProtocolPdfRenderer;
use App\Services\Protocol\ProtocolService;
use App\Services\Protocol\ProtocolSignatureTokenService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtocolController extends Controller {
    /**
     * Whitelist der erlaubten Subject-Typen für Protokolle.
     *
     * @var array<string, class-string<Model>>
     */
    private const SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'project' => Project::class,
        'customer' => Customer::class,
        'asset' => Asset::class,
    ];

    public function __construct(
        private readonly ProtocolService $service,
    ) {}

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Protocol::class);

        $data = $request->validate([
            'subject_kind' => ['required', 'string', 'in:'.implode(',', array_keys(self::SUBJECT_MAP))],
            'subject_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:'.implode(',', array_column(ProtocolType::cases(), 'value'))],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'state_initial' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:'.implode(',', array_column(ProtocolVisibility::cases(), 'value'))],
            'template_id' => ['nullable', 'integer', 'min:1'],
            'template_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $subjectClass = self::SUBJECT_MAP[$data['subject_kind']];
        /** @var Model|null $subject */
        $subject = $subjectClass::query()->find((int) $data['subject_id']);
        if ($subject === null) {
            abort(404);
        }

        /** @var User $creator */
        $creator = Auth::user();

        $protocol = $this->service->create($subject, $creator, $data);

        return redirect()
            ->back()
            ->with('success', __('protocol.flash.created'))
            ->withFragment('protocol-'.$protocol->id);
    }

    public function update(Request $request, Protocol $protocol): RedirectResponse {
        Gate::authorize('update', $protocol);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'state_initial' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'state_final' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'visibility' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_column(ProtocolVisibility::cases(), 'value'))],
            'type' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_column(ProtocolType::cases(), 'value'))],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->service->update($protocol, $actor, $data);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('protocol.flash.updated'));
    }

    public function destroy(Protocol $protocol): RedirectResponse {
        Gate::authorize('delete', $protocol);

        $protocol->delete();

        return redirect()->back()->with('success', __('protocol.flash.deleted'));
    }

    public function transition(Request $request, Protocol $protocol, string $action): RedirectResponse {
        Gate::authorize($this->actionToAbility($action), $protocol);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            match ($action) {
                'requestReview' => $this->service->requestReview($protocol, $actor),
                'returnToDraft' => $this->service->returnToDraft(
                    $protocol,
                    $actor,
                    $request->validate(['reason' => ['nullable', 'string', 'max:2000']])['reason'] ?? null,
                ),
                'sign' => $this->service->sign($protocol, $actor, $this->validateSignature($request)),
                'archive' => $this->service->archive($protocol, $actor),
                'supersede' => $this->service->supersede(
                    $protocol,
                    $actor,
                    (string) $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'],
                ),
                default => throw new InvalidArgumentException('Unbekannte Aktion: '.$action),
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
            ->with('success', __('protocol.flash.transition.'.$action))
            ->withFragment('protocol-'.$protocol->id);
    }

    public function addItem(Request $request, Protocol $protocol): RedirectResponse {
        Gate::authorize('update', $protocol);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'required' => ['nullable', 'boolean'],
            'item_type' => ['nullable', 'string', 'max:40', Rule::in(array_map(static fn ($c) => $c->value, ProtocolItemType::cases()))],
            'parent_item_id' => ['nullable', 'integer', 'exists:protocol_items,id'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->service->addItem($protocol, $actor, $data);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('protocol.flash.item.added'));
    }

    public function fillItem(Request $request, ProtocolItem $item): RedirectResponse {
        Gate::authorize('update', $item->protocol);

        $data = $request->validate([
            'result' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:5000'],
            'value_json' => ['nullable', 'array'],
        ]);

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
        Request $request,
        ProtocolItem $item,
        ProtocolItemPhotoService $photos,
    ): RedirectResponse {
        Gate::authorize('update', $item->protocol);
        /** @var User $u */
        $u = Auth::user();
        $allowGeo = $u->can(Permission::ProtocolItemPhotoViewGeo->value);

        $data = $request->validate([
            'photo' => [
                'required',
                'file',
                'image',
                'max:'.(ProtocolItemPhotoService::MAX_BYTES / 1024),
            ],
            'phase' => [
                'required',
                'string',
                Rule::in(array_column(ProtocolItemPhotoPhase::cases(), 'value')),
            ],
            'caption' => ['nullable', 'string', 'max:180'],
            'allow_geo' => ['nullable', 'boolean'],
        ]);

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

    public function issueSignatureToken(
        Request $request,
        Protocol $protocol,
        ProtocolSignatureTokenService $tokens,
    ): RedirectResponse {
        Gate::authorize('sign', $protocol);
        /** @var User|null $u */
        $u = Auth::user();
        if (! $u || ! $u->can(Permission::ProtocolSignatureRequest->value)) {
            abort(403);
        }

        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(ProtocolSignatureRole::cases(), 'value'))],
            'signer_name' => ['nullable', 'string', 'max:120'],
            'signer_email' => ['required', 'email', 'max:180'],
            'ttl_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $result = $tokens->issue($protocol, $actor, $data);

        return redirect()->back()
            ->with('success', __('protocol.signature.tokenIssued'))
            ->with('protocol.signature.token_url', route('protocols.public-sign', ['token' => $result['token']]));
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
     * @return array<string, mixed>|null
     */
    private function validateSignature(Request $request): ?array {
        if (! $request->boolean('with_signature')) {
            return null;
        }

        $data = $request->validate([
            'signature.role' => ['required', 'string', 'in:'.implode(',', array_column(ProtocolSignatureRole::cases(), 'value'))],
            'signature.signer_name' => ['required', 'string', 'max:120'],
            'signature.signer_email' => ['nullable', 'email', 'max:180'],
            'signature.method' => ['required', 'string', 'in:'.implode(',', array_column(ProtocolSignatureMethod::cases(), 'value'))],
            'signature.signature_image_path' => ['nullable', 'string', 'max:255'],
        ]);

        $sig = $data['signature'];
        $sig['ip'] = $request->ip();
        $sig['user_agent'] = (string) $request->userAgent();

        return $sig;
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
