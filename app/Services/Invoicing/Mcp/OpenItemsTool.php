<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemsTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mcp;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\DunningService;
use App\Services\Mcp\GuardedTool;
use App\Support\Sqid;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/**
 * MCP (MVP-1063): offene Posten — ausgestellte, nicht ganz bezahlte Rechnungen
 * mit offenem Betrag (Zahlungen aus Bank und Kasse, Einbehalt berücksichtigt)
 * und Summen je Währung.
 */
#[Name('open_items')]
#[Description('Offene Posten: ausgestellte, nicht vollständig bezahlte Rechnungen mit offenem Betrag, Fälligkeit und Verzugstagen; Summen je Währung.')]
#[IsReadOnly]
#[IsIdempotent]
final class OpenItemsTool extends GuardedTool {
    public function __construct(private readonly DunningService $dunning) {}

    public function schema(JsonSchema $schema): array {
        return [
            'customer' => $schema->string()->description('Kennung eines Kunden.'),
            'overdue_only' => $schema->boolean()->description('Nur überfällige Rechnungen.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Rechnungen, Standard 50.'),
        ];
    }

    protected function routeName(): string {
        return 'invoices.show';
    }

    protected function authorize(User $user): bool {
        return $user->can('viewAny', Invoice::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $data = $request->validate([
            'customer' => ['nullable', 'string', 'max:64'],
            'overdue_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $today = now()->toDateString();
        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotIn('type', [Invoice::TYPE_PROFORMA, Invoice::TYPE_CANCELLATION, Invoice::TYPE_CREDIT_NOTE])
            ->when(isset($data['customer']), static fn ($q) => $q->where('customer_id', Sqid::decode(Customer::class, (string) $data['customer']) ?? 0))
            ->when($data['overdue_only'] ?? false, static fn ($q) => $q->whereNotNull('due_on')->where('due_on', '<', $today))
            ->with('customer:id,name')
            ->orderBy('due_on')
            ->limit($this->limit($request, 50))
            ->get();

        /** @var array<string, Money> $totals */
        $totals = [];
        $items = [];
        foreach ($invoices as $invoice) {
            $open = $this->dunning->openAmount($invoice);
            $currency = $invoice->documentCurrency()->value;
            $totals[$currency] = isset($totals[$currency]) ? $totals[$currency]->plus($open) : $open;
            $items[] = [
                'id' => $invoice->sqid,
                'number' => $invoice->number,
                'customer' => ['id' => $invoice->customer->sqid, 'name' => $invoice->customer->name],
                'issued_on' => $invoice->issued_on?->toDateString(),
                'due_on' => $invoice->due_on?->toDateString(),
                'days_overdue' => $invoice->isOverdue() ? (int) $invoice->due_on?->startOfDay()->diffInDays(now()->startOfDay()) : 0,
                'total' => $invoice->total,
                'open_amount' => $open,
                'dunning_blocked' => $invoice->isDunningBlocked(),
            ];
        }

        return Response::structured(['open_items' => $items, 'totals' => $totals]);
    }
}
