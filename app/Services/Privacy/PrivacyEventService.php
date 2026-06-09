<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyEventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Privacy\{DataSubjectRequest, RequestEvent};
use App\Models\User;

/**
 * Schreibt minimierte, append-only Ereignisse in die Hash-Kette einer
 * Betroffenenanfrage. Metadaten enthalten KEINE Klartext-PII.
 */
class PrivacyEventService {
    /** @param array<string, mixed> $metadata */
    public function record(DataSubjectRequest $request, string $event, ?User $actor = null, array $metadata = []): RequestEvent {
        return RequestEvent::create([
            'organization_id' => $request->organization_id,
            'request_id' => $request->id,
            'actor_type' => $actor instanceof User ? 'staff' : 'system',
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
