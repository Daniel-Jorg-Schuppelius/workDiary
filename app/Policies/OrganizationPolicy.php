<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Organization, User};

class OrganizationPolicy {
    /**
     * NUR der globale Plattform-Betreiber erhält den Voll-Bypass über ALLE
     * Organisationen. `Organization` trägt KEINEN OrganizationScope, das
     * Route-Binding {organization} löst also jeden Mandanten global auf —
     * ein org-lokaler Admin (isAdmin() im eigenen Team-Kontext) dürfte sonst
     * fremde Mandanten auflisten, exportieren, deaktivieren und löschen
     * (Cross-Tenant). Org-lokale Admins müssen daher die self-org-Abilities
     * (view/update) einzeln durchlaufen. `manage-members` bleibt ausgenommen:
     * ein globaler Admin ohne Org-Kontext soll keinen Mitgliederzugriff haben.
     */
    public function before(User $user, string $ability): ?bool {
        if ($ability === 'manage-members') {
            return null;
        }

        return $user->isGlobalAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        return false; // Mandantenliste: nur Plattform-Betreiber (before-Hook)
    }

    /** Eigene Organisation ansehen (org-lokaler Admin) bzw. jede (Plattform). */
    public function view(User $user, Organization $organization): bool {
        return $user->isAdmin() && $user->organization_id === $organization->id;
    }

    public function create(User $user): bool {
        return false; // Mandanten anlegen: nur Plattform-Betreiber
    }

    /** Eigene Organisation bearbeiten (Einstellungen, Branding-nahe Felder). */
    public function update(User $user, Organization $organization): bool {
        return $user->isAdmin() && $user->organization_id === $organization->id;
    }

    public function delete(User $user, Organization $organization): bool {
        return false;
    }

    /**
     * Endgültiges Löschen (Purge) inkl. aller mandantengebundenen
     * Datensätze und Dateien. Wird vom OrganizationLifecycleService
     * ausgeführt; im Controller zusätzlich durch Slug-Eingabe und
     * Deaktivierungs-Cooldown abgesichert. Reserviert für globale
     * Admins (Admin-Bypass im before()-Hook).
     */
    public function purge(User $user, Organization $organization): bool {
        return false;
    }

    /**
     * Vollständigen Datenexport einer Organisation erzeugen (DSGVO Art. 20).
     * Ebenfalls admin-only via Admin-Bypass.
     */
    public function export(User $user, Organization $organization): bool {
        return false;
    }

    /**
     * Deaktivieren / Reaktivieren einer Organisation (reversibel).
     * Admin-only via Admin-Bypass.
     */
    public function deactivate(User $user, Organization $organization): bool {
        return false;
    }

    public function reactivate(User $user, Organization $organization): bool {
        return false;
    }

    /**
     * Org-Admins dürfen Mitglieder der eigenen Organisation verwalten.
     */
    public function manageMembers(User $user): bool {
        return $user->isAdmin() && $user->organization_id !== null;
    }

    /**
     * Branding (Logos, Farben, Kontakt, PDF-Header) darf ausschließlich
     * ein Admin DER betreffenden Organisation bearbeiten. Cross-Org
     * Admins werden über den `before()`-Hook bewusst durchgelassen.
     */
    public function manageBranding(User $user, Organization $organization): bool {
        return $user->isAdmin() && $user->organization_id === $organization->id;
    }
}
