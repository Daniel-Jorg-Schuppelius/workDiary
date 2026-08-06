<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphSubscriptionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Msgraph\Api\MsgraphIntakeClient;
use Illuminate\Support\{Carbon, Str};

/**
 * Sender-Seite der Graph-Change-Notifications für den Dokumenteingang
 * (MS365-Plan §8): legt Subscriptions auf dem Drive-Root der Verbindung an,
 * erneuert sie rechtzeitig (driveItem-Laufzeit < 30 Tage) und meldet sie beim
 * Trennen ab. Der Webhook bleibt reines Aufwecksignal — die Wahrheit holt
 * weiterhin die Delta-Query über den persistierten Checkpoint.
 *
 * clientState = `webhook_secret` der Verbindung (encrypted at-rest); der
 * Empfänger vergleicht in Konstantzeit ({@see \App\Plugins\Support\WebhookSignature}).
 */
class MsgraphSubscriptionService {
    /** Laufzeit neuer Subscriptions (Graph-Limit driveItem: < 30 Tage). */
    private const LIFETIME_DAYS = 29;

    /** Erneuern, wenn weniger als so viele Tage Restlaufzeit bleiben. */
    private const RENEW_THRESHOLD_DAYS = 3;

    /**
     * Subscription der Verbindung sicherstellen (anlegen/erneuern).
     * Wirft bei API-Fehlern — Aufrufer entscheiden über best-effort.
     */
    public function ensure(CloudDocumentConnection $connection): void {
        if ($connection->provider !== CloudIntakeProvider::Microsoft || trim((string) $connection->container_id) === '') {
            return;
        }

        $client = new MsgraphIntakeClient($connection);
        $expiresAt = Carbon::now()->addDays(self::LIFETIME_DAYS);

        $subscriptionId = trim((string) $connection->subscription_id);
        if ($subscriptionId !== '') {
            $current = $connection->subscription_expires_at;
            if ($current instanceof Carbon && $current->greaterThan(Carbon::now()->addDays(self::RENEW_THRESHOLD_DAYS))) {
                return; // noch lange gültig
            }
            if ($client->renewSubscription($subscriptionId, $expiresAt)) {
                $connection->forceFill(['subscription_expires_at' => $expiresAt])->save();

                return;
            }
            // 404: abgelaufen/gelöscht → unten neu anlegen.
        }

        $clientState = trim((string) $connection->webhook_secret);
        if ($clientState === '') {
            $clientState = Str::random(48);
        }

        $created = $client->createSubscription(
            route('api.webhooks.msgraph-intake'),
            '/drives/' . $connection->container_id . '/root',
            $clientState,
            $expiresAt,
        );

        $connection->forceFill([
            'subscription_id' => $created['id'],
            'subscription_expires_at' => $created['expires_at'] !== '' ? Carbon::parse($created['expires_at']) : $expiresAt,
            'webhook_secret' => $clientState,
        ])->save();
    }

    /** Abmelden beim Trennen/Deaktivieren (best effort, idempotent). */
    public function unsubscribe(CloudDocumentConnection $connection): void {
        $subscriptionId = trim((string) $connection->subscription_id);
        if ($subscriptionId === '') {
            return;
        }

        try {
            (new MsgraphIntakeClient($connection))->deleteSubscription($subscriptionId);
        } catch (\Throwable) {
            // Ohne gültiges Token nicht abmeldbar — Graph räumt abgelaufene
            // Subscriptions selbst ab (< 30 Tage Laufzeit).
        }

        $connection->forceFill(['subscription_id' => null, 'subscription_expires_at' => null])->save();
    }

    /**
     * Alle fälligen Microsoft-Verbindungen (Scheduler-Lauf).
     *
     * @return array{ensured: int, failed: int}
     */
    public function ensureAll(?int $organizationId = null): array {
        $ensured = 0;
        $failed = 0;

        $query = CloudDocumentConnection::query()
            ->withoutGlobalScopes()
            ->where('provider', CloudIntakeProvider::Microsoft->value)
            ->whereNotNull('external_account_id');
        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        foreach ($query->get() as $connection) {
            if (! $connection->status->isRunnable()) {
                continue;
            }
            try {
                $this->ensure($connection);
                $ensured++;
            } catch (\Throwable $e) {
                $failed++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        return ['ensured' => $ensured, 'failed' => $failed];
    }
}
