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

use App\Models\User;
use App\Support\Sqid;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Kanonische Auflösung des „angesehenen" Nutzers für Selbst-/Fremd-Listen
 * (Touren, Tankungen …): ohne `user`-Parameter der eingeloggte Nutzer, `all`
 * nur für Admins (→ null = alle), ein fremder Nutzer ebenfalls nur für Admins.
 *
 * Bewusst EINE Implementierung der sicherheitsrelevanten Eskalationssperre,
 * damit sie nicht je Controller neu geschrieben wird und auseinanderdriftet.
 * Nur die domänenspezifischen Fehlermeldungen werden hereingereicht.
 */
trait ResolvesRequestedUser {
    protected function resolveRequestedUserOrAll(
        Request $request,
        User $authUser,
        string $allDeniedMessage,
        string $foreignDeniedMessage,
    ): ?User {
        if (! $request->filled('user')) {
            return $authUser;
        }

        $raw = (string) $request->query('user');
        if ($raw === 'all') {
            if (! $authUser->isAdmin()) {
                throw new AccessDeniedHttpException($allDeniedMessage);
            }

            return null;
        }

        $requestedId = Sqid::decodeOrNumeric(User::class, $raw);
        if ($requestedId === null || $requestedId === (int) $authUser->id) {
            return $authUser;
        }
        if (! $authUser->isAdmin()) {
            throw new AccessDeniedHttpException($foreignDeniedMessage);
        }

        // Mandantengrenze: auch Admins lösen nur Nutzer der EIGENEN Organisation
        // auf. User trägt keinen globalen OrganizationScope, daher hier explizit
        // filtern — sonst ist der Fremd-Nutzer-Parameter ein Cross-Tenant-Zugriff
        // (Whitebox-Befund 2026-07). Org-übergreifend geht nur per Org-Wechsel.
        $target = User::query()
            ->where('organization_id', $authUser->organization_id)
            ->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }
}
