<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditPackagePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsAuditPackage;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Auditpakete (Feature 046, Inkrement E):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view/download (Pakete einsehen und
 *   intern herunterladen — isms.viewAny/view).
 * - Anlegen, Finalisieren, Integritätsprüfung und Prüfer-Token-Pflege
 *   nur mit isms.manage. Tokens haben bewusst KEINE eigene Policy —
 *   sie werden über manageTokens() hier autorisiert (analog
 *   IsmsCertificates in der IsmsNormStatusPolicy).
 * - Der öffentliche Prüfer-Download läuft NICHT über diese Policy,
 *   sondern token-basiert (Hash + Ablauf + Widerruf) ohne Login.
 */
class IsmsAuditPackagePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsManage->value);
    }

    /** Entwurf finalisieren (Snapshot + Hash, friert ein). */
    public function finalize(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsManage->value);
    }

    /** Integrität prüfen (file_hash gegen Datei). */
    public function verify(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsView->value);
    }

    /** Interner Download der Paketdatei (Gate isms.viewAny). */
    public function download(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    /** Prüfer-Tokens erstellen/widerrufen. */
    public function manageTokens(User $user, IsmsAuditPackage $package): bool {
        return $user->can(P::IsmsManage->value);
    }
}
