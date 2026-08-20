<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesCurrentOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

/**
 * Auflösung der für den Request gebundenen Organisation (Single Source of
 * Truth: das von SetOrganizationContext gebundene Modell).
 *
 * `currentOrganization()` ist die STRIKTE Variante (non-nullable, 403) für
 * Controller, die ohne gültigen Org-Kontext gar nicht arbeiten dürfen.
 *
 * Audit 2026-08 (W2.6): Die weicheren Varianten waren in neun Controllern
 * je eigenständig nachgebaut — mit *bewusst* unterschiedlicher Semantik
 * (nullable, `int`-Variante, 404 statt 403 „keine Existenz-Auskunft", oder
 * mit User-Fallback für Kontexte ohne Middleware). Statt diese Semantik
 * einzuebnen (was Sicherheits- und Fehlerverhalten verändert hätte), bietet
 * der Trait sie jetzt als benannte Varianten an — der Aufrufer wählt bewusst,
 * die Duplikate entfallen.
 */
trait ResolvesCurrentOrganization {
    protected function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);

        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    /**
     * Wie {@see currentOrganization()}, aber mit wählbarem Status — 404 dort,
     * wo die bloße Existenz eines Org-Kontexts keine Auskunft geben soll
     * (Support-Zugriff/Impersonation).
     */
    protected function currentOrganizationOrAbort(int $status = 403): Organization {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        abort_unless($organization instanceof Organization, $status);

        return $organization;
    }

    /** Weiche Variante: null, wenn kein Org-Kontext gebunden ist. */
    protected function currentOrganizationOrNull(): ?Organization {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        return $organization instanceof Organization ? $organization : null;
    }

    /**
     * Weiche Variante mit User-Fallback: für Oberflächen, die auch ohne
     * gebundenen Kontext noch die Organisation des Anmelde-Nutzers meinen
     * (z. B. Branding-/Nummernkreis-Einstellungen).
     */
    protected function currentOrganizationOrUserOrganization(): ?Organization {
        $organization = $this->currentOrganizationOrNull();
        if ($organization instanceof Organization) {
            return $organization;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->organization;
    }

    /** ID-Variante; 0, wenn kein Kontext gebunden ist (Alt-Verhalten). */
    protected function currentOrganizationId(): int {
        $organization = $this->currentOrganizationOrNull();

        return $organization instanceof Organization ? (int) $organization->id : 0;
    }
}
