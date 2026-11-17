<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Exceptions\CustomerDeletionBlockedException;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SaveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer\Customer;
use App\Services\Customer\CustomerDeletion;
use App\Services\Stammdaten\ContactDetailsWriter;
use Illuminate\Http\{Request, Response};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CustomerInput',
    description: 'Kunde anlegen oder ändern; weitere Felder wie im Kundendialog. Beim Ändern gelten nur gesendete Felder, fehlende bleiben unverändert; neu angelegt gelten die Vorgaben (EUR, abrechenbar).',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 200),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR', description: 'fehlt das Feld, bleibt die Währung; neu angelegt EUR'),
        new OA\Property(property: 'number', type: 'string', maxLength: 64, nullable: true),
        new OA\Property(property: 'company', type: 'string', maxLength: 200, nullable: true),
        new OA\Property(property: 'vat_id', type: 'string', maxLength: 64, nullable: true),
        new OA\Property(property: 'register_number', type: 'string', maxLength: 200, nullable: true, description: 'Registerangabe, etwa „HRB 12345, Amtsgericht Musterstadt“ (BT-47 der E-Rechnung)'),
        new OA\Property(property: 'is_consumer', type: 'boolean', description: 'Verbraucher statt Unternehmen (Leistungsort nach § 3a UStG); fehlt das Feld, bleibt der Wert, neu angelegt gilt Unternehmen'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'phone', type: 'string', maxLength: 64, nullable: true),
        new OA\Property(property: 'address_street', type: 'string', maxLength: 255, nullable: true, description: 'Rechnungsanschrift; fehlende Felder bleiben unverändert'),
        new OA\Property(property: 'address_zip', type: 'string', maxLength: 32, nullable: true),
        new OA\Property(property: 'address_city', type: 'string', maxLength: 128, nullable: true),
        new OA\Property(property: 'country', type: 'string', description: 'ISO 3166-1 alpha-2', nullable: true),
        new OA\Property(property: 'bank_account_holder', type: 'string', maxLength: 200, nullable: true, description: 'Bankverbindung; fehlende Felder bleiben unverändert'),
        new OA\Property(property: 'bank_iban', type: 'string', maxLength: 64, nullable: true),
        new OA\Property(property: 'bank_bic', type: 'string', maxLength: 32, nullable: true),
        new OA\Property(property: 'bank_name', type: 'string', maxLength: 200, nullable: true),
        new OA\Property(property: 'shipping_street', type: 'string', maxLength: 255, nullable: true, description: 'Abweichende Lieferanschrift; fehlende Felder bleiben unverändert, alle leer = keine'),
        new OA\Property(property: 'shipping_supplement', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'shipping_zip', type: 'string', maxLength: 32, nullable: true),
        new OA\Property(property: 'shipping_city', type: 'string', maxLength: 128, nullable: true),
        new OA\Property(property: 'shipping_country', type: 'string', description: 'ISO 3166-1 alpha-2', nullable: true),
        new OA\Property(property: 'billable', type: 'boolean', description: 'abrechenbar; fehlt das Feld, bleibt der Wert, neu angelegt true'),
        new OA\Property(
            property: 'contact_persons',
            type: 'array',
            nullable: true,
            description: 'weitere Ansprechpartner als ganze Liste; fehlt das Feld, bleiben sie, [] oder null leert sie',
            items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'salutation', type: 'string', nullable: true),
                new OA\Property(property: 'first_name', type: 'string', nullable: true),
                new OA\Property(property: 'last_name', type: 'string', nullable: true),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'primary', type: 'boolean'),
            ]),
        ),
    ],
    type: 'object',
)]
class CustomerController extends Controller {
    use ResolvesCurrentOrganization;

    #[OA\Get(
        path: '/customers',
        summary: 'Kunden auflisten',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'archived', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Customer::class);
        $query = Customer::query();
        if ($request->boolean('archived') === false) {
            $query->whereNull('archived_at');
        }
        if ($search = $request->string('search')->toString()) {
            $query->whereLikeEscaped('name', $search);
        }

        return CustomerResource::collection($query->orderBy('name')->paginate($this->perPage($request)));
    }

    #[OA\Post(
        path: '/customers',
        summary: 'Kunde anlegen',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CustomerInput')),
        responses: [new OA\Response(response: 201, description: 'Created')],
    )]
    public function store(SaveCustomerRequest $request): CustomerResource {
        Gate::authorize('create', Customer::class);
        $data = $request->validated();
        $contact = ContactDetailsWriter::pullInline($data);
        $customer = Customer::create($data + [
            'created_by' => Auth::id(),
            'organization_id' => $this->currentOrganization()->id,
        ]);
        app(ContactDetailsWriter::class)->writeInline($customer, $contact);

        // refresh() statt fresh(): die Projektion ist nachgezogen, und die Instanz bleibt „neu“ (201).
        return new CustomerResource($customer->refresh());
    }

    #[OA\Get(
        path: '/customers/{customer}',
        summary: 'Kunde anzeigen',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK'), new OA\Response(response: 404, description: 'Not Found')],
    )]
    public function show(Customer $customer): CustomerResource {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    #[OA\Put(
        path: '/customers/{customer}',
        summary: 'Kunde aktualisieren',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CustomerInput')),
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Customer $customer, SaveCustomerRequest $request): CustomerResource {
        Gate::authorize('update', $customer);
        $data = $request->validated();
        // Anschrift, Bank und Lieferanschrift über den Satelliten (MVP-1694, MVP-1700): nur gesendete
        // Felder ändern sich, Altbestand ohne Satellit bekommt ihn vorher aus der Projektion.
        $contact = ContactDetailsWriter::pullInline($data);
        $customer->update($data);
        app(ContactDetailsWriter::class)->writeInline($customer, $contact);

        return new CustomerResource($customer->fresh() ?? $customer);
    }

    #[OA\Delete(
        path: '/customers/{customer}',
        summary: 'Kunde löschen',
        tags: ['Customers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'No Content')],
    )]
    public function destroy(Customer $customer): Response {
        Gate::authorize('delete', $customer);
        // Dieselben Sperren und Folgen wie in der Oberfläche (MVP-1591): Legal Hold 423, sonst 409.
        try {
            app(CustomerDeletion::class)->delete($customer);
        } catch (CustomerDeletionBlockedException $e) {
            abort($e->status(), $e->getMessage());
        }

        return response()->noContent();
    }

    /**
     * Seitengröße klemmen (Sicherheitsscan 2026-08-23, S-58): `per_page` ging
     * ungeprüft an paginate() — ein Token konnte damit den gesamten Bestand in
     * einer Antwort anfordern.
     */
    private function perPage(Request $request): int {
        return max(1, min(100, (int) $request->input('per_page', 25)));
    }

}
