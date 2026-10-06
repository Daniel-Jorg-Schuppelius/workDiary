<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreateQuoteDraftTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Mcp;

use App\Enums\Api\ApiAbility;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Sales\Quote;
use App\Services\Invoicing\QuoteService;
use App\Services\Mcp\GuardedTool;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};

/** MCP (MVP-1064): Angebotsentwurf mit Positionen — freigeben und versenden bleibt in workDiary. */
#[Name('create_quote_draft')]
#[Description('Legt einen Angebotsentwurf mit Positionen für einen Kunden an. Das Angebot bleibt Entwurf; Freigabe und Versand erfolgen in workDiary. Preise netto als Dezimalzahl.')]
final class CreateQuoteDraftTool extends GuardedTool {
    public function __construct(private readonly QuoteService $quotes) {}

    public function ability(): ApiAbility {
        return ApiAbility::McpWrite;
    }

    public function schema(JsonSchema $schema): array {
        return [
            'customer' => $schema->string()->required()->description('Kennung des Kunden.'),
            'project' => $schema->string()->description('Kennung eines Projekts des Kunden.'),
            'valid_until' => $schema->string()->format('date')->description('Bindefrist (JJJJ-MM-TT).'),
            'terms' => $schema->string()->description('Einleitungs- oder Konditionstext.'),
            'items' => $schema->array()->required()->description('Positionen: description, quantity, unit_price (netto), optional unit, tax_rate, article (Kennung eines Artikels).'),
        ];
    }

    protected function routeName(): string {
        return 'quotes.index';
    }

    protected function authorize(User $user): bool {
        return $user->can('create', Quote::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'customer' => ['required', 'string', 'max:64'],
            'project' => ['nullable', 'string', 'max:64'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required'],
            'items.*.unit_price' => ['required'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.tax_rate' => ['nullable'],
            'items.*.article' => ['nullable', 'string', 'max:64'],
        ]);
        $customer = Customer::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(Customer::class, $data['customer']) ?? 0)->first();
        if ($customer === null) {
            return Response::error((string) __('mcp.error.not_found'));
        }
        $project = isset($data['project'])
            ? Project::query()->where('organization_id', $organization->id)->where('customer_id', $customer->id)->whereKey(Sqid::decode(Project::class, $data['project']) ?? 0)->first()
            : null;
        if (isset($data['project']) && $project === null) {
            return Response::error((string) __('mcp.error.not_found'));
        }

        $items = [];
        foreach ($data['items'] as $i => $item) {
            $quantity = NumberHelper::normalizeDecimalStringOrNull((string) $item['quantity']);
            $price = NumberHelper::normalizeDecimalStringOrNull((string) $item['unit_price']);
            $taxRate = isset($item['tax_rate']) ? NumberHelper::normalizeDecimalStringOrNull((string) $item['tax_rate']) : null;
            if ($quantity === null || $price === null || (isset($item['tax_rate']) && $taxRate === null)) {
                return Response::error((string) __('mcp.error.invalid_number', ['position' => $i + 1]));
            }
            $articleId = isset($item['article'])
                ? Article::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(Article::class, (string) $item['article']) ?? 0)->value('id')
                : null;
            $items[] = array_filter([
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
                'article_id' => $articleId,
            ], static fn ($v): bool => $v !== null);
        }

        $quote = $this->quotes->create([
            'customer_id' => $customer->id,
            'project_id' => $project?->id,
            'valid_until' => $data['valid_until'] ?? null,
            'terms' => $data['terms'] ?? null,
        ], $items, $user);
        $quote->audit('mcp.write', $this->writeContext($user));

        return Response::structured([
            'id' => $quote->sqid,
            'number' => $quote->number,
            'status' => $quote->status->value,
            'total' => $quote->total,
            'url' => route('quotes.show', $quote),
        ]);
    }
}
