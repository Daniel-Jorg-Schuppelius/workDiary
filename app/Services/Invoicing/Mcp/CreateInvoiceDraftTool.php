<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreateInvoiceDraftTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mcp;

use App\Enums\Api\ApiAbility;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Sales\Quote;
use App\Services\Invoicing\{InvoiceGenerator, QuoteService};
use App\Services\Mcp\GuardedTool;
use App\Support\{ErrorText, Sqid};
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use RuntimeException;

/** MCP (MVP-1064): Rechnungsentwurf aus offenen Zeiten oder aus einem angenommenen Angebot — ausstellen bleibt in workDiary. */
#[Name('create_invoice_draft')]
#[Description('Legt einen Rechnungsentwurf an: `source=time` aus den offenen, abrechenbaren Zeiten eines Kunden (optional Projekt und Zeitraum) oder `source=quote` aus einem angenommenen Angebot. Ausstellen und Versenden erfolgen in workDiary.')]
final class CreateInvoiceDraftTool extends GuardedTool {
    public function __construct(
        private readonly InvoiceGenerator $invoices,
        private readonly QuoteService $quotes,
    ) {}

    public function ability(): ApiAbility {
        return ApiAbility::McpWrite;
    }

    public function schema(JsonSchema $schema): array {
        return [
            'source' => $schema->string()->enum(['time', 'quote'])->required()->description('Quelle des Entwurfs.'),
            'customer' => $schema->string()->description('Kennung des Kunden (bei source=time).'),
            'project' => $schema->string()->description('Kennung eines Projekts (bei source=time, optional).'),
            'from' => $schema->string()->format('date')->description('Zeiten ab (JJJJ-MM-TT, optional).'),
            'to' => $schema->string()->format('date')->description('Zeiten bis (JJJJ-MM-TT, optional).'),
            'quote' => $schema->string()->description('Kennung des angenommenen Angebots (bei source=quote).'),
        ];
    }

    protected function routeName(): string {
        return 'invoices.show';
    }

    protected function authorize(User $user): bool {
        return $user->can('create', Invoice::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'source' => ['required', 'in:time,quote'],
            'customer' => ['required_if:source,time', 'nullable', 'string', 'max:64'],
            'project' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'quote' => ['required_if:source,quote', 'nullable', 'string', 'max:64'],
        ]);

        try {
            $invoice = $data['source'] === 'quote' ? $this->fromQuote($data, $user, $organization) : $this->fromTime($data, $organization);
        } catch (RuntimeException $e) {
            return Response::error(ErrorText::for($e));
        }
        if ($invoice === null) {
            return Response::error((string) __('mcp.error.not_found'));
        }
        $invoice->audit('mcp.write', $this->writeContext($user));

        return Response::structured([
            'id' => $invoice->sqid,
            'status' => $invoice->status,
            'total' => $invoice->total,
            'positions' => $invoice->items()->count(),
            'url' => route('invoices.show', $invoice),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function fromTime(array $data, Organization $organization): ?Invoice {
        $customer = Customer::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(Customer::class, (string) $data['customer']) ?? 0)->first();
        $project = isset($data['project'])
            ? Project::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(Project::class, (string) $data['project']) ?? 0)->first()
            : null;
        if ($customer === null || (isset($data['project']) && $project === null)) {
            return null;
        }

        return $this->invoices->fromTimeEntries($customer, $project, array_filter(['from' => $data['from'] ?? null, 'to' => $data['to'] ?? null]));
    }

    /** @param  array<string, mixed>  $data */
    private function fromQuote(array $data, User $user, Organization $organization): ?Invoice {
        $quote = Quote::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(Quote::class, (string) $data['quote']) ?? 0)->first();
        if ($quote === null || ! $user->can('view', $quote)) {
            return null;
        }

        return $this->quotes->convertToInvoice($quote, $user);
    }
}
