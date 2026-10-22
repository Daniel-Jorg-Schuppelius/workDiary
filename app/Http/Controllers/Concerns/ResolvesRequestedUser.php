<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesRequestedUser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Kanonische Auflösung des „angesehenen" Nutzers für Selbst-/Fremd-Listen:
 * ohne `user`-Parameter der eingeloggte Nutzer, `all` (→ null) und fremder
 * Nutzer nur für Admins oder wen der Aufrufer per `$seesOthers` zulässt
 * (Verwaltungsrecht). Bewusst EINE Implementierung der sicherheitsrelevanten
 * Eskalationssperre; nur Meldungen und Freigabe werden hereingereicht.
 */
trait ResolvesRequestedUser {
    protected function resolveRequestedUserOrAll(
        Request $request,
        User $authUser,
        string $allDeniedMessage,
        string $foreignDeniedMessage,
        ?bool $seesOthers = null,
    ): ?User {
        $seesOthers ??= $authUser->isAdmin();
        if (! $request->filled('user')) {
            return $authUser;
        }

        $raw = (string) $request->query('user');
        if ($raw === 'all') {
            if (! $seesOthers) {
                throw new AccessDeniedHttpException($allDeniedMessage);
            }

            return null;
        }

        $requestedId = Sqid::decodeOrNumeric(User::class, $raw);
        if ($requestedId === null || $requestedId === (int) $authUser->id) {
            return $authUser;
        }
        if (! $seesOthers) {
            throw new AccessDeniedHttpException($foreignDeniedMessage);
        }

        // Mandantengrenze explizit (User hat keinen globalen OrganizationScope): sonst ist der Fremd-Nutzer-Parameter ein Cross-Tenant-Zugriff (Whitebox-Befund 2026-07).
        $target = User::query()
            ->where('organization_id', $authUser->organization_id)
            ->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }
}
