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

namespace App\Policies\Sales;

use App\Enums\Sales\QuoteStatus;
use App\Models\Platform\User;
use App\Models\Sales\Quote;

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
        return $user->canManageBilling() && $quote->status === QuoteStatus::Draft;
    }

    /**
     * Nachfassen (Feature 112, MVP-601) — eigene Fähigkeit, NICHT `update`.
     *
     * `update` erlaubt bewusst nur Entwürfe: Ein versandtes Angebot darf
     * inhaltlich nicht mehr verändert werden. Das Nachfassen ändert aber auch
     * nichts am Angebot, sondern hält einen Kontakt fest — es passiert genau
     * dann, wenn `update` zu Recht schon gesperrt ist.
     */
    public function followUp(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status->isPending();
    }

    public function delete(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status === QuoteStatus::Draft;
    }

    public function approve(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status->canTransitionTo(QuoteStatus::Approved);
    }

    public function send(User $user, Quote $quote): bool {
        return $user->canManageBilling() && $quote->status->canTransitionTo(QuoteStatus::Sent);
    }

    /** Interne Entscheidung (Annahme/Ablehnung dokumentieren) + Versionierung. */
    public function decide(User $user, Quote $quote): bool {
        return $user->canManageBilling();
    }

    public function convert(User $user, Quote $quote): bool {
        return $user->canManageBilling()
            && $quote->status->isWon();
    }
}
