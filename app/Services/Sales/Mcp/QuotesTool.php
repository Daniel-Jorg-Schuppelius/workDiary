<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuotesTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Mcp;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\{Quote, QuoteItem};
use App\Services\Mcp\GuardedTool;
use App\Support\Sqid;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Angebote auflisten oder eines mit Positionen zeigen. */
#[Name('quotes')]
#[Description('Angebote nach Status oder Kunde auflisten; mit `id` ein Angebot mit Positionen und Summen anzeigen.')]
#[IsReadOnly]
#[IsIdempotent]
final class QuotesTool extends GuardedTool {
    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->description('Kennung eines Angebots für die Detailansicht.'),
            'status' => $schema->string()->enum(Quote::STATUSES)->description('Status des Angebots.'),
            'customer' => $schema->string()->description('Kennung eines Kunden.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Treffer, Standard 20.'),
        ];
    }

    protected function routeName(): string {
        return 'quotes.index';
    }

    protected function authorize(User $user): bool {
        return $user->can('viewAny', Quote::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:' . implode(',', Quote::STATUSES)],
            'customer' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $quotes = Quote::query()->where('organization_id', $organization->id)->with('customer:id,name');

        if (isset($data['id'])) {
            $quote = $quotes->with('items')->whereKey(Sqid::decode(Quote::class, $data['id']) ?? 0)->first();
            if ($quote === null || ! $user->can('view', $quote)) {
                return Response::error((string) __('mcp.error.not_found'));
            }

            return Response::structured(['quote' => [
                ...self::row($quote),
                'terms' => $quote->terms,
                'items' => $quote->items->map(static fn (QuoteItem $item): array => [
                    'id' => $item->sqid,
                    'kind' => $item->lineKind()->value,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'optional' => (bool) $item->optional,
                ])->all(),
            ]]);
        }

        $list = $quotes
            ->when(isset($data['status']), static fn ($q) => $q->where('status', $data['status']))
            ->when(isset($data['customer']), static fn ($q) => $q->where('customer_id', Sqid::decode(Customer::class, (string) $data['customer']) ?? 0))
            ->orderByDesc('id')
            ->limit($this->limit($request))
            ->get();

        return Response::structured(['quotes' => $list->map(static fn (Quote $quote): array => self::row($quote))->all()]);
    }

    /** @return array<string, mixed> */
    private static function row(Quote $quote): array {
        return [
            'id' => $quote->sqid,
            'number' => $quote->number,
            'version' => $quote->version,
            'status' => $quote->status,
            'customer' => $quote->customer !== null ? ['id' => $quote->customer->sqid, 'name' => $quote->customer->name] : null,
            'valid_until' => $quote->valid_until?->toDateString(),
            'subtotal' => $quote->subtotal,
            'tax_amount' => $quote->tax_amount,
            'total' => $quote->total,
        ];
    }
}
