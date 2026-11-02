<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Retention;

use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/**
 * Übernommene oder verworfene Fernwartungssitzungen (Leistungsanalyse
 * 2026-11-02, perf-11): die Zeit lebt am Zeiteintrag weiter, die Sitzung
 * selbst trägt nur noch Gerätekennung und Zeitpunkt.
 */
final class RemoteSupportRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            new RetentionPolicy(
                area: 'remote_pending_sessions',
                modelClass: RemotePendingSession::class,
                overdueQuery: fn($organization, $cutoff) => RemotePendingSession::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', [RemotePendingSessionStatus::Imported->value, RemotePendingSessionStatus::Dismissed->value])
                    ->where('ended_at', '<', $cutoff),
            ),
        ];
    }
}
