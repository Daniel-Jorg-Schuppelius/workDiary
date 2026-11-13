<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreateCustomerTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Mcp;

use App\Enums\Api\ApiAbility;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Rules\IsoCountryCode;
use App\Services\Mcp\GuardedTool;
use App\Services\Stammdaten\ContactDetailsWriter;
use App\Settings\SettingsRegistry;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};

/** MCP (MVP-1064): Kunde anlegen; ein Kunde mit derselben USt-IdNr. wird gemeldet statt doppelt angelegt. */
#[Name('create_customer')]
#[Description('Legt einen Kunden mit Kontaktdaten, Anschrift, Registernummer, Merkmal Verbraucher und optional abweichender Lieferanschrift an. Gibt es schon einen Kunden mit derselben USt-IdNr., wird dieser gemeldet und nichts angelegt.')]
final class CreateCustomerTool extends GuardedTool {
    public function __construct(private readonly ContactDetailsWriter $contacts) {}

    public function ability(): ApiAbility {
        return ApiAbility::McpWrite;
    }

    public function schema(JsonSchema $schema): array {
        return [
            'name' => $schema->string()->required()->description('Name bzw. Anzeigename des Kunden.'),
            'company' => $schema->string()->description('Firmenname; leer bei Privatkunden.'),
            'vat_id' => $schema->string()->description('USt-IdNr.'),
            'register_number' => $schema->string()->description('Registerangabe, etwa „HRB 12345, Amtsgericht Musterstadt“.'),
            'is_consumer' => $schema->boolean()->description('Verbraucher (Privatperson) statt Unternehmen; bestimmt den Leistungsort nach § 3a UStG. Ohne Angabe: Unternehmen.'),
            'contact_name' => $schema->string()->description('Ansprechpartner.'),
            'email' => $schema->string()->format('email')->description('E-Mail-Adresse.'),
            'phone' => $schema->string()->description('Telefon.'),
            'address_street' => $schema->string()->description('Straße und Hausnummer.'),
            'address_zip' => $schema->string()->description('Postleitzahl.'),
            'address_city' => $schema->string()->description('Ort.'),
            'country' => $schema->string()->description('Ländercode nach ISO 3166-1 (z. B. DE).'),
            'shipping_street' => $schema->string()->description('Abweichende Lieferanschrift: Straße und Hausnummer; leer = Lieferung an die Anschrift oben.'),
            'shipping_supplement' => $schema->string()->description('Lieferanschrift: Adresszusatz.'),
            'shipping_zip' => $schema->string()->description('Lieferanschrift: Postleitzahl.'),
            'shipping_city' => $schema->string()->description('Lieferanschrift: Ort.'),
            'shipping_country' => $schema->string()->description('Lieferanschrift: Ländercode nach ISO 3166-1.'),
        ];
    }

    protected function routeName(): string {
        return 'customers.index';
    }

    protected function authorize(User $user): bool {
        return $user->can('create', Customer::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'company' => ['nullable', 'string', 'max:200'],
            'vat_id' => ['nullable', 'string', 'max:64'],
            'register_number' => ['nullable', 'string', 'max:200'],
            'is_consumer' => ['nullable', 'boolean'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:20'],
            'address_city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:2', new IsoCountryCode],
            'shipping_street' => ['nullable', 'string', 'max:255'],
            'shipping_supplement' => ['nullable', 'string', 'max:255'],
            'shipping_zip' => ['nullable', 'string', 'max:32'],
            'shipping_city' => ['nullable', 'string', 'max:128'],
            'shipping_country' => ['nullable', 'string', 'max:2', new IsoCountryCode],
        ]);
        foreach (['country', 'shipping_country'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper((string) $data[$field]);
            }
        }
        if (array_key_exists('is_consumer', $data)) {
            $data['is_consumer'] = (bool) $data['is_consumer'];
        }
        if (($data['vat_id'] ?? '') !== '') {
            $existing = Customer::query()->where('organization_id', $organization->id)->whereIdentifier('vat_id', (string) $data['vat_id'])->first();
            if ($existing !== null) {
                return Response::structured(['created' => false, 'existing' => ['id' => $existing->sqid, 'name' => $existing->name]]);
            }
        }

        $inline = ContactDetailsWriter::pullInline($data);
        $currency = CurrencyCode::tryFrom(strtoupper((string) app(SettingsRegistry::class)->effective('invoicing.default_currency', $organization)->value)) ?? CurrencyCode::Euro;
        $customer = DB::transaction(function () use ($data, $inline, $organization, $user, $currency): Customer {
            $customer = Customer::query()->create([
                ...$data,
                'organization_id' => $organization->id,
                'currency' => $currency->value,
                'created_by' => $user->id,
            ]);
            $this->contacts->writeInline($customer, $inline);

            return $customer;
        });
        $customer->audit('mcp.write', $this->writeContext($user));

        return Response::structured(['created' => true, 'id' => $customer->sqid, 'name' => $customer->name, 'url' => route('customers.show', $customer)]);
    }
}
