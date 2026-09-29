<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistExpiryProbe.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Services;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\Plugins\Todoist\TodoistConnection;
use App\Services\Operations\Expiry\ExpiryProbe;
use App\Services\Operations\OperationsSignal;
use App\Support\Setting;

/** Ablaufende OAuth-Tokens und gestörte Todoist-Verbindungen als Betriebsaufgabe (bis MVP-1044 im ExpiryScanner). */
final class TodoistExpiryProbe implements ExpiryProbe {
    public function signals(): array {
        $leadDays = (int) Setting::get('operations.expiry.credential_days', 14);
        $signals = [];

        foreach (TodoistConnection::query()->withoutGlobalScopes()->get() as $connection) {
            $orgId = (int) $connection->organization_id;
            if ($connection->token_expires_at !== null
                && $connection->token_expires_at->isFuture()
                && $connection->token_expires_at->lte(now()->addDays($leadDays))) {
                $signals[] = new OperationsSignal(
                    type: OperationsTaskType::CredentialExpiring,
                    dedupeKey: 'credential_expiring:todoist:' . $connection->id,
                    severity: OperationsTaskSeverity::Warning,
                    titleKey: 'operations.task.credential_expiring',
                    params: [
                        'kind' => 'OAuth-Token (Todoist)',
                        'name' => (string) ($connection->todoist_user_email ?? 'Todoist'),
                        'date' => $connection->token_expires_at->toDateString(),
                    ],
                    organizationId: $orgId,
                );
            }
            if ((string) $connection->status !== 'active' || $connection->last_error !== null) {
                $signals[] = new OperationsSignal(
                    type: OperationsTaskType::ConnectionFailing,
                    dedupeKey: 'connection_failing:todoist:' . $connection->id,
                    severity: OperationsTaskSeverity::Warning,
                    titleKey: 'operations.task.connection_failing',
                    params: [
                        'name' => (string) ($connection->todoist_user_email ?? 'Todoist'),
                        'kind' => 'Todoist',
                        'error' => (string) ($connection->last_error ?? $connection->status),
                    ],
                    organizationId: $orgId,
                );
            }
        }

        return $signals;
    }
}
