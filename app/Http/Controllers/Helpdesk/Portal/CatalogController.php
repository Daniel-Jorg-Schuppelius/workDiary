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

use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Procurement\RequestItem;
use App\Models\ServiceTicket\ServiceRequest;
use App\Services\Fields\{FieldSchema, FieldValidator, FieldValues};
use App\Services\ServiceTicket\ServiceRequestService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Portal-Bestellstrecke (Feature 065, MVP-154): kundensichtbarer Katalog
 * (visibility.portal, optional customer_ids), Bestellformular aus der
 * 032-Vorlage des Katalogeintrags, Bestellung friert Formular + Katalog-
 * stand ein (ServiceRequestService::submit im Portal-Kontext). Sichtbarkeit
 * wird serverseitig geprüft — nicht sichtbare Einträge enden 404.
 * Datei-/Foto-/Signatur-Felder werden im Portal bewusst nicht gerendert
 * (kein Upload-Kanal in der Bestellstrecke).
 */
class CatalogController extends Controller {
    public function __construct(
        private readonly ServiceRequestService $service,
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

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
