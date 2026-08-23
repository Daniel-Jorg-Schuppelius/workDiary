<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingEventRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\{AccountingEntry, AccountingEvent};
use App\Models\{Organization, User};

/**
 * Schreibt die revisionssichere Ereigniskette des Buchungskerns
 * (Feature 125, MVP-672).
 *
 * Einziger Schreibweg auf {@see AccountingEvent}: Der Nachweis darf nicht an
 * verstreuten Stellen entstehen, sonst fehlt er irgendwann genau dort, wo er
 * gebraucht wird.
 */
class AccountingEventRecorder {
    /** @param array<string, mixed> $payload */
    public function record(Organization $organization, string $event, array $payload = [], ?AccountingEntry $entry = null, ?User $actor = null): AccountingEvent {
        return AccountingEvent::query()->create([
            'organization_id' => $organization->id,
            'accounting_entry_id' => $entry?->id,
            'event' => $event,
            'actor_user_id' => $actor?->id,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }
}
