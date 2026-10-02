<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ai.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Mcp\McpOAuthController;
use App\Http\Middleware\{EnforcePlanModules, RequireMcpScope};
use App\Services\Mcp\WorkDiaryMcpServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// MCP-Server (MVP-1063). laravel/mcp lädt diese Datei ohne Gruppe — der
// api-Stack bringt Organisationskontext, Drosselung und Wartungsmodus.
Route::middleware('api')->group(function (): void {
    Mcp::web('mcp', WorkDiaryMcpServer::class)
        ->middleware(['auth:sanctum', RequireMcpScope::class, EnforcePlanModules::class])
        ->name('mcp');

    // OAuth für Web-Connectoren (MVP-1065). Der Name der verschachtelten
    // Metadaten-Route ist der, den laravel/mcp in WWW-Authenticate verlinkt.
    Route::get('.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResource'])->name('mcp.oauth.protected-resource');
    Route::get('.well-known/oauth-protected-resource/{path}', [McpOAuthController::class, 'protectedResource'])->where('path', '.*')->name('mcp.oauth.protected-resource.nested');
    Route::get('.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])->name('mcp.oauth.authorization-server');
    Route::post('oauth/register', [McpOAuthController::class, 'register'])->middleware('throttle:10,1')->name('mcp.oauth.register');
    Route::post('oauth/token', [McpOAuthController::class, 'token'])->middleware('throttle:30,1')->name('mcp.oauth.token');
    Route::post('oauth/revoke', [McpOAuthController::class, 'revoke'])->middleware('throttle:30,1')->name('mcp.oauth.revoke');
});
