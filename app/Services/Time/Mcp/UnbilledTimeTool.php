<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnbilledTimeTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Time\Mcp;

use App\Models\Platform\{Organization, User};
use App\Services\Mcp\GuardedTool;
use App\Services\Time\Chain\UnbilledTimeByCustomer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): abrechenbare, noch nicht abgerechnete Zeiten je Kunde — wie die Liste „Offene Zeiten“. */
#[Name('unbilled_time')]
#[Description('Abrechenbare, noch nicht abgerechnete Zeiten je Kunde: Anzahl der Einträge, Minuten und ältester Eintrag.')]
#[IsReadOnly]
#[IsIdempotent]
final class UnbilledTimeTool extends GuardedTool {
    public function __construct(private readonly UnbilledTimeByCustomer $source) {}

    public function schema(JsonSchema $schema): array {
        return [
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Kunden, Standard 20.'),
        ];
    }

    protected function routeName(): string {
        return $this->source->routeName();
    }

    protected function authorize(User $user): bool {
        return $this->source->availableFor($user);
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return Response::structured(['customers' => array_map(static fn (array $row): array => [
            'customer' => $row['customer'] !== null ? ['id' => $row['customer']->sqid, 'name' => $row['customer']->name] : null,
            'entries' => $row['entries'],
            'minutes' => $row['minutes'],
            'oldest' => $row['oldest']->toDateString(),
        ], $this->source->rows($organization, $this->limit($request)))]);
    }
}
