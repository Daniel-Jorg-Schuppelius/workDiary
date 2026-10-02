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

use App\Http\Resources\InvoiceResource;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\DunningService;
use App\Services\Mcp\GuardedTool;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Rechnungen auflisten oder eine mit Positionen und offenem Betrag zeigen. */
#[Name('invoices')]
#[Description('Rechnungen nach Status, Kunde oder Rechnungsdatum auflisten; mit `id` eine Rechnung mit Positionen und offenem Betrag anzeigen.')]
#[IsReadOnly]
#[IsIdempotent]
final class InvoicesTool extends GuardedTool {
    public function __construct(private readonly DunningService $dunning) {}

    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->description('Kennung einer Rechnung für die Detailansicht.'),
            'status' => $schema->string()->enum(Invoice::STATUSES)->description('Status der Rechnung.'),
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
            'status' => ['nullable', 'string', 'in:' . implode(',', Invoice::STATUSES)],
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
                'open_amount' => in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID], true) ? $this->dunning->openAmount($invoice) : null,
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

        return Response::structured(['invoices' => $list->map(static fn (Invoice $invoice): array => InvoiceResource::make($invoice)->resolve())->all()]);
    }
}
