<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardedTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Enums\Api\ApiAbility;
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Mcp\Contracts\McpTool;
use App\Services\Mcp\OAuth\McpAuthorizationServer;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Tool;

/**
 * Werkzeug des MCP-Servers (MVP-1063) mit denselben Schranken wie die
 * Oberfläche: Token-Scope, Organisation (fail-closed, Opt-in `mcp.enabled`), Modul-Gate der
 * zugehörigen Route und Policy. Ein Werkzeug, das nicht passt, erscheint
 * nicht in `tools/list` und ist auch nicht aufrufbar.
 */
abstract class GuardedTool extends Tool implements McpTool {
    /** Route der Oberfläche, deren Modul-Gate auch hier gilt. */
    abstract protected function routeName(): string;

    /** Dieselbe Policy wie die Liste bzw. Seite der Oberfläche. */
    abstract protected function authorize(User $user): bool;

    abstract protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory;

    public function ability(): ApiAbility {
        return ApiAbility::McpRead;
    }

    public function shouldRegister(Request $request): bool {
        return $this->actor($request) !== null;
    }

    public function handle(Request $request): Response|ResponseFactory {
        $actor = $this->actor($request);
        if ($actor === null) {
            return Response::error((string) __('mcp.error.forbidden'));
        }

        return $this->respond($request, ...$actor);
    }

    /** Trefferzahl aus `limit`, begrenzt auf 1–50. */
    protected function limit(Request $request, int $default = 20): int {
        return max(1, min(50, (int) ($request->get('limit') ?? $default)));
    }

    /**
     * Kontext des Audit-Eintrags `mcp.write`, den jedes schreibende Werkzeug am
     * Gegenstand setzt — zusätzlich zu den fachlichen Ereignissen.
     *
     * @return array{tool: string, token: string|null}
     */
    protected function writeContext(User $user): array {
        $name = data_get($user->currentAccessToken(), 'name');

        return ['tool' => $this->name(), 'token' => is_string($name) ? $name : null];
    }

    /** @return array{0: User, 1: Organization}|null */
    private function actor(Request $request): ?array {
        $user = $request->user();
        // Der Sanctum-Guard fragt die Kontosperre nicht ab — hier zählt sie für jedes Werkzeug (pub-2).
        if (! $user instanceof User || ! $user->canLogin() || ! $this->tokenAllows($user)) {
            return null;
        }
        $organization = $user->organization;
        if (! $organization instanceof Organization || ! app(McpAuthorizationServer::class)->enabledFor($organization)) {
            return null;
        }
        $module = app(ModuleRegistry::class)->moduleForRoute($this->routeName());
        if ($module !== null && ! app(ModuleStatusResolver::class)->isActiveFor($organization, $module)) {
            return null;
        }

        return $this->authorize($user) ? [$user, $organization] : null;
    }

    /** Lesen erlaubt auch ein Schreib-Token; Schreiben nur `mcp:write`. */
    private function tokenAllows(User $user): bool {
        if ($this->ability() === ApiAbility::McpWrite) {
            return $user->tokenCan(ApiAbility::McpWrite->value);
        }

        return $user->tokenCan(ApiAbility::McpRead->value) || $user->tokenCan(ApiAbility::McpWrite->value);
    }
}
