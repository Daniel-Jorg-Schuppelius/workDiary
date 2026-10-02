<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Mcp;

use App\Models\Platform\{Organization, User};
use App\Services\Mcp\GuardedTool;
use App\Services\Search\GlobalSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): globale Suche mit denselben rechte- und mandantensicheren Gruppen wie die Oberfläche. */
#[Name('search')]
#[Description('Volltextsuche über Kunden, Projekte, Objekte, Dokumente, Mitarbeitende und Tätigkeiten (Aufträge, Zeiten, Protokolle, Kommentare). Liefert Treffer je Gruppe mit Titel, Untertitel und Link.')]
#[IsReadOnly]
#[IsIdempotent]
final class SearchTool extends GuardedTool {
    public function __construct(private readonly GlobalSearchService $search) {}

    public function schema(JsonSchema $schema): array {
        return [
            'query' => $schema->string()->min(2)->required()->description('Suchbegriff, mindestens zwei Zeichen.'),
            'limit' => $schema->integer()->min(1)->max(20)->description('Treffer je Gruppe, Standard 5.'),
        ];
    }

    protected function routeName(): string {
        return 'search.index';
    }

    protected function authorize(User $user): bool {
        return true;
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return Response::structured(['groups' => $this->search->groups($user, $data['query'], [], (int) ($data['limit'] ?? 5))]);
    }
}
