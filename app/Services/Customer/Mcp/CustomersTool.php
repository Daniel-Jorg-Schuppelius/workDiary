<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomersTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Mcp;

use App\Http\Resources\CustomerResource;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Mcp\GuardedTool;
use App\Support\Sqid;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Kunden suchen oder einen Kunden mit seinen Projekten zeigen. */
#[Name('customers')]
#[Description('Kunden suchen (Name, Firma, Kundennummer) oder mit `id` einen Kunden samt Projekten anzeigen.')]
#[IsReadOnly]
#[IsIdempotent]
final class CustomersTool extends GuardedTool {
    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->description('Kennung eines Kunden für die Detailansicht.'),
            'query' => $schema->string()->description('Suchtext in Name, Firma oder Kundennummer.'),
            'include_archived' => $schema->boolean()->description('Archivierte Kunden einschließen.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Treffer, Standard 20.'),
        ];
    }

    protected function routeName(): string {
        return 'customers.index';
    }

    protected function authorize(User $user): bool {
        return $user->can('viewAny', Customer::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'query' => ['nullable', 'string', 'max:120'],
            'include_archived' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $customers = Customer::query()->where('organization_id', $organization->id);

        if (isset($data['id'])) {
            $customer = $customers->whereKey(Sqid::decode(Customer::class, $data['id']) ?? 0)->first();
            if ($customer === null || ! $user->can('view', $customer)) {
                return Response::error((string) __('mcp.error.not_found'));
            }

            return Response::structured([
                'customer' => CustomerResource::make($customer)->resolve(),
                'projects' => $customer->projects()->orderBy('name')->limit(50)->get(['id', 'name', 'number', 'status', 'archived_at'])
                    ->map(static fn ($project): array => ['id' => $project->sqid, 'name' => $project->name, 'number' => $project->number, 'status' => $project->status, 'archived' => $project->archived_at !== null])
                    ->all(),
            ]);
        }

        $term = trim((string) ($data['query'] ?? ''));
        $list = $customers
            ->when(! ($data['include_archived'] ?? false), static fn ($q) => $q->whereNull('archived_at'))
            ->when($term !== '', static fn ($q) => $q->where(static fn ($w) => $w->whereLikeEscaped('name', $term)->orWhereLikeEscaped('company', $term)->orWhereLikeEscaped('number', $term)))
            ->orderBy('name')
            ->limit($this->limit($request))
            ->get();

        return Response::structured(['customers' => $list->map(static fn (Customer $c): array => CustomerResource::make($c)->resolve())->all()]);
    }
}
