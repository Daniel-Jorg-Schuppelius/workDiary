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

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Form\FormFieldType;
use App\Http\Controllers\Controller;
use App\Models\{RequestItem, ServiceRequest, User};
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
        $fields = $this->renderableFields($item);
        if ($fields === []) {
            return [];
        }

        $rules = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $required = (bool) ($field['required'] ?? false);
            $rules["values.{$key}"] = match ((string) ($field['type'] ?? '')) {
                FormFieldType::Number->value => [$required ? 'required' : 'nullable', 'numeric'],
                FormFieldType::Date->value => [$required ? 'required' : 'nullable', 'date'],
                FormFieldType::Select->value => [$required ? 'required' : 'nullable', 'string', \Illuminate\Validation\Rule::in((array) ($field['options'] ?? []))],
                FormFieldType::Checkbox->value => [$required ? 'accepted' : 'nullable', 'boolean'],
                default => [$required ? 'required' : 'nullable', 'string', 'max:10000'],
            };
        }

        $validated = $request->validate($rules);

        $answers = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $answers[$key] = $validated['values'][$key] ?? null;
        }

        return $answers;
    }

    /**
     * Im Portal renderbare Felder: Upload-/Signatur-Typen werden ausgelassen.
     *
     * @return list<array<string, mixed>>
     */
    private function renderableFields(RequestItem $item): array {
        $skip = [FormFieldType::Photo->value, FormFieldType::File->value, FormFieldType::Signature->value];

        return array_values(array_filter(
            (array) ($item->formTemplate->fields ?? []),
            fn(array $field): bool => ! in_array((string) $field['type'], $skip, true),
        ));
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
