<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Protocol;

use App\Enums\Protocol\{ProtocolItemPhotoPhase, ProtocolItemType};
use App\Enums\User\Permission;
use App\Exceptions\{InvalidProtocolTransitionException, ProtocolValidationException};
use App\Http\Controllers\Controller;
use App\Http\Requests\Protocol\{AddProtocolItemRequest, FillProtocolItemRequest, IssueProtocolSignatureTokenRequest, StoreProtocolRequest, TransitionProtocolRequest, UpdateProtocolRequest, UploadProtocolItemPhotoRequest};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Protocol\{Protocol, ProtocolItem, ProtocolItemPhoto};
use App\Services\Protocol\Fields\ProtocolItemFields;
use App\Services\Protocol\{ProtocolItemPhotoService, ProtocolPdfRenderer, ProtocolService, ProtocolSignatureTokenService, ProtocolTemplateService};
use App\Services\Weather\WeatherService;
use App\Support\{ErrorText, Sqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\View\View;
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

    /** Punktarten im Dialog; Unterschrift, Prozedurschritt und interne Abnahme entstehen über ihre Abläufe. */
    public const FORM_ITEM_TYPES = [
        ProtocolItemType::Group, ProtocolItemType::Text, ProtocolItemType::Boolean, ProtocolItemType::Choice,
        ProtocolItemType::Multichoice, ProtocolItemType::Number, ProtocolItemType::Range, ProtocolItemType::Date,
        ProtocolItemType::DateTime, ProtocolItemType::Photo, ProtocolItemType::Defect, ProtocolItemType::MeasurementTimestamped,
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
            'journal.actor:id,name',
            'attachments',
            'tags',
        ]);

        return view('protocols.show', [
            'protocol' => $protocol,
        ]);
    }

    /** Anlegedialog am Bezug (MVP-883): Auftrag, Projekt, Kunde oder Objekt. */
    public function create(Request $request): View {
        Gate::authorize('create', Protocol::class);

        $kind = $request->string('subject_kind')->toString();
        $class = self::SUBJECT_MAP[$kind] ?? null;
        $id = $class !== null ? Sqid::decode($class, $request->string('subject')->toString()) : null;
        $subject = $id !== null ? $class::query()->find($id) : null;
        abort_unless($subject instanceof Model, 404);
        Gate::authorize('view', $subject);

        return view('protocols._form_dialog', [
            'subjectKind' => $kind,
            'subject' => $subject,
            'templates' => app(ProtocolTemplateService::class)->applicableFor($subject),
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
            ->route('protocols.show', $protocol)
            ->with('success', __('protocol.flash.created'));
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

    /** Dialog für Übergänge mit Eingabe (MVP-883): Unterschrift, Begründung. */
    public function transitionForm(Protocol $protocol, string $action): View {
        abort_unless(in_array($action, ['sign', 'returnToDraft', 'supersede'], true), 404);
        Gate::authorize($this->actionToAbility($action), $protocol);

        return view('protocols._transition_dialog', [
            'protocol' => $protocol,
            'action' => $action,
        ]);
    }

    /** Dialog „Signaturlink senden“ (MVP-883). */
    public function signatureTokenForm(Protocol $protocol): View {
        Gate::authorize('sign', $protocol);
        abort_unless(Auth::user()?->can(Permission::ProtocolSignatureRequest->value) ?? false, 403);

        return view('protocols._signature_token_dialog', ['protocol' => $protocol]);
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
            return redirect()->back()->withErrors(['reason' => ErrorText::for($e)]);
        } catch (ProtocolValidationException $e) {
            return redirect()->back()->withErrors(['validation' => implode(' • ', $e->errors())]);
        }

        return redirect()
            ->back()
            ->with('success', __('protocol.flash.transition.' . $action))
            ->withFragment('protocol-' . $protocol->id);
    }

    /** Dialog „Punkt hinzufügen“ (MVP-883). */
    public function itemForm(Protocol $protocol): View {
        Gate::authorize('update', $protocol);

        return view('protocols._item_dialog', [
            'protocol' => $protocol,
            'types' => self::FORM_ITEM_TYPES,
            'groups' => $protocol->items()->where('item_type', ProtocolItemType::Group->value)->orderBy('sort_order')->get(),
        ]);
    }

    public function addItem(AddProtocolItemRequest $request, Protocol $protocol): RedirectResponse {
        Gate::authorize('update', $protocol);

        $data = $request->validated();
        $config = $this->itemConfig($data);
        if ($config !== []) {
            $data['value_json'] = $config;
        }
        unset($data['options'], $data['unit'], $data['min'], $data['max']);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->service->addItem($protocol, $actor, $data);
        } catch (InvalidProtocolTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('protocol.flash.item.added'));
    }

    /** Dialog „Ausfüllen“ je Punkt (MVP-883), Eingabe über das Feldschema. */
    public function fillForm(ProtocolItem $item, ProtocolItemFields $fields): View {
        Gate::authorize('update', $item->protocol);

        return view('protocols._fill_dialog', [
            'item' => $item,
            'field' => $fields->definition($item),
            'value' => $fields->value($item),
            'fillable' => ! in_array($item->item_type, [ProtocolItemType::Group, ProtocolItemType::Photo, ProtocolItemType::File, ProtocolItemType::Signature, ProtocolItemType::ProcedureStep, ProtocolItemType::SignoffInternal], true),
        ]);
    }

    public function fillItem(FillProtocolItemRequest $request, ProtocolItem $item, ProtocolItemFields $fields): RedirectResponse {
        Gate::authorize('update', $item->protocol);

        $data = $request->validated();
        if (array_key_exists('values', $data)) {
            $mapped = $fields->fromInput($item, ((array) $data['values'])[ProtocolItemFields::key($item)] ?? null);
            if ($mapped !== null) {
                $data['value_json'] = $mapped;
            }
            unset($data['values']);
        }

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
            return redirect()->back()->withErrors(['photo' => ErrorText::for($e)]);
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
        \App\Models\Protocol\ProtocolSignatureToken $token,
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
            return redirect()->back()->with('error', ErrorText::for($e));
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

    /**
     * Konfiguration eines neuen Punkts: Auswahlwerte (eine je Zeile, Schlüssel
     * aus dem Text), Einheit und Grenzen — in der Form, die der Feld-Adapter liest.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function itemConfig(array $data): array {
        $config = [];
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['options'] ?? '')) ?: []), static fn (string $l): bool => $l !== ''));
        if ($lines !== []) {
            $used = [];
            foreach ($lines as $index => $label) {
                $key = Str::slug($label) ?: 'option-' . ($index + 1);
                while (isset($used[$key])) {
                    $key .= '-' . ($index + 1);
                }
                $used[$key] = true;
                $config['options'][] = ['key' => $key, 'label' => $label];
            }
        }
        if (($data['unit'] ?? null) !== null && $data['unit'] !== '') {
            $config['unit'] = (string) $data['unit'];
        }
        foreach (['min', 'max'] as $bound) {
            if (isset($data[$bound]) && is_numeric($data[$bound])) {
                $config[$bound] = $data[$bound] + 0;
            }
        }

        return $config;
    }

    private function actionToAbility(string $action): string {
        return match ($action) {
            'requestReview' => 'requestReview',
            'returnToDraft' => 'returnToDraft',
            'sign' => 'sign',
            'archive' => 'archive',
            'supersede' => 'supersede',
            default => 'update',
        };
    }
}
