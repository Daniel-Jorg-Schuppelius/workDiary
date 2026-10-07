<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk\Portal;

use App\Enums\Customer\IntakeKind;
use App\Enums\CustomerPortal\PortalCapability;
use App\Http\Controllers\Concerns\{ValidatesIntakeSubmission, ValidatesUploadedFiles};
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\ServiceTicket\ServiceRequest;
use App\Services\Attachments\FileAttacher;
use App\Services\Customer\Intake\{CustomerIntakeService, IntakeTemplates};
use App\Services\CustomerPortal\PortalVisibility;
use App\Services\Fields\{FieldDefinition, FieldDocument, FieldSchema, FieldValidator, FieldValues};
use App\Services\ServiceTicket\ServiceRequestService;
use App\Support\Sqid;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Portal-Bestellstrecke (Feature 065, MVP-154): kundensichtbarer Katalog
 * (visibility.portal, optional customer_ids), Bestellformular aus der
 * 032-Vorlage des Katalogeintrags, Bestellung friert Formular + Katalog-
 * stand ein (ServiceRequestService::submit im Portal-Kontext). Sichtbarkeit
 * wird serverseitig geprüft — nicht sichtbare Einträge enden 404.
 * Datei-/Foto-/Signatur-Felder werden in der Direktbestellung bewusst nicht
 * gerendert (kein Upload-Kanal). Der Anfragepfad (MVP-1077) legt stattdessen
 * einen Kundeneingang an: Datei-/Foto-Felder mit Prüfung und geschütztem
 * Download, Ticket und Fulfillment erst nach angenommenem Angebot.
 */
class CatalogController extends Controller {
    use ValidatesIntakeSubmission;
    use ValidatesUploadedFiles;

    public function __construct(
        private readonly ServiceRequestService $service,
        private readonly PortalVisibility $visibility,
    ) {}

    public function index(): View {
        $user = $this->portalUser();

        return view('customer.catalog.index', [
            'items' => $this->service->visibleItemsForPortal($user),
            // Bestellstatus im Portal: eigene Requests des Kunden.
            'requests' => ServiceRequest::query()
                ->whereHas('ticket', fn($q) => $q->where('customer_id', $user->customer_id))
                ->with(['ticket:id,ticket_no,title,status', 'requestItem:id,name'])
                ->orderByDesc('created_at')
                ->paginate(25),
        ]);
    }

    public function show(RequestItem $item): View {
        $user = $this->portalUser();
        abort_unless($this->service->isPortalVisible($item, $user), 404);

        return view('customer.catalog.show', [
            'item' => $item->loadMissing('formTemplate'),
            'fields' => $this->renderableFields($item),
            // Anfragepfad (MVP-1077) nur mit Freigabe „Anfragen und Aufträge".
            'canRequest' => $user->customer !== null && $this->visibility->allows($user->customer, PortalCapability::Intakes),
        ]);
    }

    public function order(Request $request, RequestItem $item): RedirectResponse {
        $user = $this->portalUser();
        abort_unless($this->service->isPortalVisible($item, $user), 404);

        $answers = $this->validatedAnswers($request, $item);

        $serviceRequest = $this->service->submit($item, $user, $answers, viaPortal: true);

        return redirect()->route('customer.tickets.show', $serviceRequest->ticket()->firstOrFail())
            ->with('success', __('Bestellung übermittelt.'));
    }

    /** Anfrage statt Bestellung (MVP-1077): Formular des Kundeneingangs mit der Katalogvorlage. */
    public function requestForm(RequestItem $item): View {
        $user = $this->portalUser();
        abort_unless($this->service->isPortalVisible($item, $user), 404);

        return view('customer.intakes.create', [
            'kind' => IntakeKind::It,
            'schema' => app(IntakeTemplates::class)->schema(IntakeKind::It),
            'assets' => $user->customer !== null ? $this->visibility->assetsFor($user->customer) : collect(),
            'submissionKey' => (string) Str::uuid(),
            'action' => route('customer.catalog.request.store', $item),
            'catalogItem' => $item,
            'catalogSchema' => $this->requestFields($item),
        ]);
    }

    public function request(Request $request, RequestItem $item, CustomerIntakeService $intakes): RedirectResponse|JsonResponse {
        $user = $this->portalUser();
        abort_unless($this->service->isPortalVisible($item, $user), 404);

        [$data, $form] = $this->validatedIntake($request, IntakeKind::It);
        $catalogSchema = $this->requestFields($item);
        $values = $this->validatedCatalogValues($request, $catalogSchema);
        $fieldFiles = $this->validatedFieldFiles($request, $catalogSchema->visibleFor($values->toArray()));
        $markers = [];
        foreach ($fieldFiles as $key => $file) {
            $markers[$key] = File::sanitizeDisplayName($file->getClientOriginalName());
        }
        $files = $this->validatedUploads($request, IntakeKind::It->uploadPurpose()->maxFiles(), IntakeKind::It->uploadPurpose(), 'uploads');

        $asset = null;
        $assetSqid = (string) $request->input('asset', '');
        if ($assetSqid !== '' && $user->customer !== null) {
            $assetId = Sqid::decode(Asset::class, $assetSqid);
            $asset = $this->visibility->assetsFor($user->customer)->first(fn (Asset $candidate): bool => (int) $candidate->id === $assetId) ?? abort(422);
        }

        $intake = $intakes->submit($user, $data, $form, $files, $item, new FieldDocument($catalogSchema, $values->with($markers)), $fieldFiles, $asset);

        return $this->intakeResponse($request, route('customer.intakes.show', $intake), (string) __('customer_intake.flash.submitted', ['number' => $intake->number]));
    }

    /**
     * Antworten gegen die AKTIVE Felddefinition der Vorlage validieren —
     * Pflichtfelder erzwingen, nur bekannte Feld-Keys übernehmen.
     *
     * @return array<string, mixed>
     */
    private function validatedAnswers(Request $request, RequestItem $item): array {
        $schema = $this->renderableFields($item);
        if ($schema->isEmpty()) {
            return [];
        }
        $validated = $request->validate(app(FieldValidator::class)->rules($schema), [], $schema->attributeNames());

        return FieldValues::normalize($schema, (array) ($validated['values'] ?? []))->toArray();
    }

    /** Im Portal renderbare Felder: Upload-/Signatur-Typen werden ausgelassen (kein Dateikanal). */
    private function renderableFields(RequestItem $item): FieldSchema {
        return FieldSchema::fromArray($item->formTemplate->fields ?? [])->withoutAttachments();
    }

    /** Felder des Anfragepfads: Datei- und Fotofelder ja, Unterschriften nicht (kein Signaturkanal im Portal). */
    private function requestFields(RequestItem $item): FieldSchema {
        $fields = FieldSchema::fromArray($item->formTemplate->fields ?? [])->all();

        return new FieldSchema(array_values(array_filter($fields, static fn (FieldDefinition $field): bool => ! $field->type->isSignature())));
    }

    /**
     * Datei-/Fotofelder (`files[<key>]`): allgemeine Positivliste und Grenze,
     * Pflichtfelder über den Feldschema-Baustein.
     *
     * @return array<string, UploadedFile>
     */
    private function validatedFieldFiles(Request $request, FieldSchema $schema): array {
        $files = [];
        $errors = [];
        foreach ($schema as $field) {
            $file = $field->type->isUpload() ? $request->file('files.' . $field->key) : null;
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! $file->isValid() || $file->getSize() > FileAttacher::effectiveMaxKb() * 1024 || ! FileAttacher::accepts($file)) {
                $errors['files.' . $field->key] = (string) __('uploads.error.type', ['name' => '„' . File::sanitizeDisplayName($file->getClientOriginalName()) . '“']);

                continue;
            }
            $files[$field->key] = $file;
        }
        $errors += app(FieldValidator::class)->missingAttachments($schema, $files, [], [], 'catalog');
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $files;
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
