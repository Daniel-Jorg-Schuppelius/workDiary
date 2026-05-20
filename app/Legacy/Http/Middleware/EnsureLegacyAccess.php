<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureLegacyAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Legacy\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Blockiert Zugriff auf den Legacy-Bereich für User ohne legacy_user_id
 * (und ohne Admin-Rolle).
 */
class EnsureLegacyAccess {
    public function handle(Request $request, Closure $next): Response {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessLegacy()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Kein Zugriff auf das Legacy-System.'),
                ], 403);
            }

            throw new AccessDeniedHttpException(__('Kein Zugriff auf das Legacy-System.'));
        }

        return $next($request);
    }
}
