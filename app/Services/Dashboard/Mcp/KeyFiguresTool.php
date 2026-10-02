<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyFiguresTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dashboard\Mcp;

use App\Models\Platform\{Organization, User};
use App\Services\Dashboard\DashboardService;
use App\Services\Mcp\GuardedTool;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Kennzahlen des Dashboards — dieselben, die die Person dort sieht. */
#[Name('key_figures')]
#[Description('Kennzahlen aus dem Dashboard: eigene Zeiten und Aufträge, für Berechtigte Team und Finanzen (Umsatz, offene Forderungen).')]
#[IsReadOnly]
#[IsIdempotent]
final class KeyFiguresTool extends GuardedTool {
    public function __construct(private readonly DashboardService $dashboard) {}

    protected function routeName(): string {
        return 'dashboard';
    }

    protected function authorize(User $user): bool {
        return true;
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $summary = $this->dashboard->summarize($user);

        return Response::structured([
            'as_of' => $summary['now']->toIso8601String(),
            'personal' => $summary['user'],
            'team' => $summary['team'],
            'finance' => $summary['finance'],
        ]);
    }
}
