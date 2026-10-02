<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectsTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Mcp;

use App\Http\Resources\ProjectResource;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Mcp\GuardedTool;
use App\Support\Sqid;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Projekte suchen, nach Kunde filtern oder ein Projekt zeigen. */
#[Name('projects')]
#[Description('Projekte suchen oder nach Kunde filtern; mit `id` ein Projekt mit Kunde anzeigen.')]
#[IsReadOnly]
#[IsIdempotent]
final class ProjectsTool extends GuardedTool {
    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->description('Kennung eines Projekts für die Detailansicht.'),
            'query' => $schema->string()->description('Suchtext in Name oder Projektnummer.'),
            'customer' => $schema->string()->description('Kennung eines Kunden.'),
            'include_archived' => $schema->boolean()->description('Archivierte Projekte einschließen.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Treffer, Standard 20.'),
        ];
    }

    protected function routeName(): string {
        return 'projects.index';
    }

    protected function authorize(User $user): bool {
        return $user->can('viewAny', Project::class);
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'query' => ['nullable', 'string', 'max:120'],
            'customer' => ['nullable', 'string', 'max:64'],
            'include_archived' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $projects = Project::query()->where('organization_id', $organization->id)->with('customer:id,name');

        if (isset($data['id'])) {
            $project = $projects->whereKey(Sqid::decode(Project::class, $data['id']) ?? 0)->first();
            if ($project === null || ! $user->can('view', $project)) {
                return Response::error((string) __('mcp.error.not_found'));
            }

            return Response::structured(['project' => [...ProjectResource::make($project)->resolve(), 'customer_name' => $project->customer?->name]]);
        }

        $term = trim((string) ($data['query'] ?? ''));
        $list = $projects
            ->when(! ($data['include_archived'] ?? false), static fn ($q) => $q->whereNull('archived_at'))
            ->when(isset($data['customer']), static fn ($q) => $q->where('customer_id', Sqid::decode(Customer::class, (string) $data['customer']) ?? 0))
            ->when($term !== '', static fn ($q) => $q->where(static fn ($w) => $w->whereLikeEscaped('name', $term)->orWhereLikeEscaped('number', $term)))
            ->orderBy('name')
            ->limit($this->limit($request))
            ->get();

        return Response::structured(['projects' => $list->map(static fn (Project $p): array => [...ProjectResource::make($p)->resolve(), 'customer_name' => $p->customer?->name])->all()]);
    }
}
