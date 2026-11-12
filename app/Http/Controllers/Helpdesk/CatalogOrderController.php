<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Procurement\RequestItem;
use App\Models\ServiceTicket\ServiceRequest;
use App\Services\Fields\{FieldSchema, FieldValidator, FieldValues};
use App\Services\ServiceTicket\ServiceRequestService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Interne Katalogbestellung (Feature 065, MVP-1549): Mitarbeitende bestellen
 * einen Katalogeintrag für sich. Sichtbarkeit nach Rollen prüft der Dienst;
 * Upload- und Signaturfelder entfallen wie im Kundenportal (kein Dateikanal).
 */
class CatalogOrderController extends Controller {
    public function __construct(private readonly ServiceRequestService $service) {}

    public function index(): View {
        Gate::authorize(Permission::ServiceCatalogRequest->value);
        $user = $this->authUser();

        return view('helpdesk.order.index', [
            'requests' => ServiceRequest::query()
                ->whereHas('ticket', fn ($q) => $q->where('reported_by_user_id', $user->id))
                ->with(['ticket', 'requestItem:id,name'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    /** Auswahldialog: was die Person nach Rolle bestellen darf. */
    public function pick(): View {
        Gate::authorize(Permission::ServiceCatalogRequest->value);

        return view('helpdesk.order._pick_dialog', [
            'items' => $this->service->visibleItems($this->authUser()),
        ]);
    }

    public function create(RequestItem $item): View {
        Gate::authorize(Permission::ServiceCatalogRequest->value);
        $this->assertVisible($item);

        return view('helpdesk.order._order_dialog', [
            'item' => $item,
            'fields' => $this->fields($item),
        ]);
    }

    public function store(Request $request, RequestItem $item): RedirectResponse {
        Gate::authorize(Permission::ServiceCatalogRequest->value);
        $this->assertVisible($item);

        $schema = $this->fields($item);
        $answers = [];
        if (! $schema->isEmpty()) {
            $validated = $request->validate(app(FieldValidator::class)->rules($schema), [], $schema->attributeNames());
            $answers = FieldValues::normalize($schema, (array) ($validated['values'] ?? []))->toArray();
        }

        $this->service->submit($item, $this->authUser(), $answers);

        return redirect()->route('servicedesk.order.index')
            ->with('success', __('Bestellung übermittelt.'));
    }

    private function assertVisible(RequestItem $item): void {
        abort_unless($this->service->visibleItems($this->authUser())->contains('id', $item->id), 404);
    }

    private function fields(RequestItem $item): FieldSchema {
        return FieldSchema::fromArray($item->formTemplate->fields ?? [])->withoutAttachments();
    }
}
