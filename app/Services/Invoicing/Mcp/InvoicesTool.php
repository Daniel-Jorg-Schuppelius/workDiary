<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicesTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mcp;

use App\Enums\Invoicing\InvoiceStatus;
use App\Http\Resources\InvoiceResource;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceQuery};
use App\Services\Invoicing\DunningService;
use App\Services\Mcp\GuardedTool;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Rechnungen auflisten oder eine mit Positionen und offenem Betrag zeigen. */
#[Name('invoices')]
#[Description('Rechnungen nach Status, Kunde oder Rechnungsdatum auflisten, dazu Rechnungen und Gutschriften aus dem Buchhaltungsprogramm (`external_invoices`); mit `id` eine lokale Rechnung mit Positionen und offenem Betrag anzeigen.')]
#[IsReadOnly]
#[IsIdempotent]
final class InvoicesTool extends GuardedTool {
    public function __construct(private readonly DunningService $dunning) {}

    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->description('Kennung einer Rechnung für die Detailansicht.'),
            'status' => $schema->string()->enum(InvoiceStatus::values())->description('Status der Rechnung.'),
            'customer' => $schema->string()->description('Kennung eines Kunden.'),
            'from' => $schema->string()->format('date')->description('Rechnungsdatum ab (JJJJ-MM-TT).'),
            'to' => $schema->string()->format('date')->description('Rechnungsdatum bis (JJJJ-MM-TT).'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Treffer, Standard 20.'),
        ];
    }

    protected function routeName(): string {
        return 'invoices.show';
    }

    protected function authorize(User $user): bool {
        return $user->can('viewAny', Invoice::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:' . implode(',', InvoiceStatus::values())],
            'customer' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $invoices = Invoice::query()->where('organization_id', $organization->id)->with('customer:id,name');

        if (isset($data['id'])) {
            $invoice = $invoices->with('items')->whereKey(Sqid::decode(Invoice::class, $data['id']) ?? 0)->first();
            if ($invoice === null || ! $user->can('view', $invoice)) {
                return Response::error((string) __('mcp.error.not_found'));
            }

            return Response::structured(['invoice' => [
                ...InvoiceResource::make($invoice)->resolve(),
                'open_amount' => in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true) ? $this->dunning->openAmount($invoice) : null,
                'items' => $invoice->items->map(static fn (InvoiceItem $item): array => [
                    'kind' => $item->lineKind()->value,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                ])->all(),
            ]]);
        }

        $list = $invoices
            ->when(isset($data['status']), static fn ($q) => $q->where('status', $data['status']))
            ->when(isset($data['customer']), static fn ($q) => $q->where('customer_id', Sqid::decode(Customer::class, (string) $data['customer']) ?? 0))
            ->when(isset($data['from']), static fn ($q) => $q->where('issued_on', '>=', DateRange::day($data['from'])))
            ->when(isset($data['to']), static fn ($q) => $q->where('issued_on', '<', DateRange::dayAfter($data['to'])))
            ->orderByDesc('issued_on')->orderByDesc('id')
            ->limit($this->limit($request))
            ->get();

        return Response::structured([
            'invoices' => $list->map(static fn (Invoice $invoice): array => InvoiceResource::make($invoice)->resolve())->all(),
            'external_invoices' => $this->external($data, $organization, $this->limit($request)),
        ]);
    }

    /**
     * Belege des Buchhaltungsprogramms (MVP-1116) — dieselbe Belegregel wie
     * die Auswertungen; Entwürfe und Stornos gibt es dort nicht.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function external(array $data, Organization $organization, int $limit): array {
        if (isset($data['status']) && ! in_array($data['status'], array_map(static fn (InvoiceStatus $s): string => $s->value, Invoice::ISSUED_STATUSES), true)) {
            return [];
        }
        $invoices = app(ExternalRevenue::class)->invoices(new ExternalInvoiceQuery(
            issuedFrom: isset($data['from']) ? CarbonImmutable::parse((string) $data['from']) : null,
            issuedTo: isset($data['to']) ? CarbonImmutable::parse((string) $data['to']) : null,
            customerId: isset($data['customer']) ? (Sqid::decode(Customer::class, (string) $data['customer']) ?? 0) : null,
            organizationId: (int) $organization->id,
        ));
        if (isset($data['status'])) {
            $invoices = array_filter($invoices, static fn (ExternalInvoice $invoice): bool => $invoice->status->value === $data['status']);
        }
        $invoices = array_slice(array_reverse(array_values($invoices)), 0, $limit);
        $customers = Customer::query()->whereIn('id', array_filter(array_map(static fn (ExternalInvoice $invoice): ?int => $invoice->customerId, $invoices)))->get(['id', 'name'])->keyBy('id');

        return array_map(static function (ExternalInvoice $invoice) use ($customers): array {
            $currency = CurrencyCode::tryFrom($invoice->currency) ?? CurrencyCode::Euro;
            $customer = $invoice->customerId !== null ? $customers->get($invoice->customerId) : null;

            return [
                'source' => $invoice->source,
                'number' => $invoice->number,
                'type' => $invoice->type,
                'status' => $invoice->status->value,
                'customer' => $customer !== null ? ['id' => $customer->sqid, 'name' => $customer->name] : null,
                'contact_name' => $customer === null ? $invoice->contactName : null,
                'issued_on' => $invoice->issuedOn?->toDateString(),
                'due_on' => $invoice->dueOn?->toDateString(),
                'paid_on' => $invoice->paidOn?->toDateString(),
                'total' => Money::ofFloat($invoice->gross, $currency),
                'open_amount' => Money::ofFloat($invoice->open, $currency),
            ];
        }, $invoices);
    }
}
