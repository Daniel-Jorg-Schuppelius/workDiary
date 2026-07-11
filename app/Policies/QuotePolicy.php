<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuotePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\{Quote, User};

/**
 * Angebote (Feature 066, MVP-170): gleiche Rechte-Basis wie Rechnungen
 * (Abrechnungsrecht) — Änderungen nur am Entwurf, nach Versand wird
 * versioniert statt geändert (QuoteService).
 */
class QuotePolicy {
    public function viewAny(User $user): bool {
        return $user->canManageBilling();
    }

    public function view(User $user, Quote $quote): bool {
        return $user->canManageBilling();
    }

    public function create(User $user): bool {
        return $user->canManageBilling();
    }

    public function update(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status === 'draft';
    }

    public function delete(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status === 'draft';
    }

    public function approve(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status === 'draft';
    }

    public function send(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status === 'approved';
    }

    /** Interne Entscheidung (Annahme/Ablehnung dokumentieren) + Versionierung. */
    public function decide(User $user, Quote $quote): bool {
        return $user->canManageBilling();
    }

    public function convert(User $user, Quote $quote): bool {
        return $user->canManageBilling()
            && in_array($quote->status, ['accepted', 'partially_accepted'], true);
    }
}
