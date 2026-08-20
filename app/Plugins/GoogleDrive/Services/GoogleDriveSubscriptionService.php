<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveSubscriptionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleDrive\Services;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\GoogleDrive\Api\GoogleDriveClient;
use Illuminate\Support\{Carbon, Str};
use Throwable;

/**
 * Sender-Seite der Drive-Push-Kanäle (Feature 080; Erweiterungs-Audit 2026-08,
 * W4.4). Der Empfänger
 * ({@see \App\Plugins\GoogleDrive\Http\Controllers\GoogleDriveWebhookController})
 * existierte bereits, die Kanäle wurden bislang nie angelegt — der Eingang lief
 * also faktisch nur über das 15-Minuten-Polling.
 *
 * Unterschiede zu Microsoft Graph, die das Muster prägen:
 *  - Google-Kanäle laufen **maximal ~24 Stunden** (`changes.watch`); es gibt
 *    kein Verlängern, nur Neuanlegen. Der Lauf ist deshalb täglich fällig und
 *    legt praktisch bei jedem Durchgang neu an.
 *  - Zum Beenden verlangt Google neben der Kanal-ID die `resourceId` aus der
 *    Watch-Antwort ({@see CloudDocumentConnection::$subscription_resource_id}).
 *  - Der Kanal trägt keine Nutzdaten: die Benachrichtigung weckt nur den
 *    regulären `changes`-Lauf über den persistierten Checkpoint — der bleibt
 *    die wiederanlaufbare Wahrheit.
 */
class GoogleDriveSubscriptionService {
    /** Kanal-Laufzeit; Google deckelt `changes.watch` bei ~24 Stunden. */
    private const LIFETIME_SECONDS = 82800; // 23 h

    /** Neu anlegen, wenn weniger als so viele Minuten Restlaufzeit bleiben. */
    private const RENEW_THRESHOLD_MINUTES = 180;

    /** Push-Kanal der Verbindung sicherstellen; wirft bei API-Fehlern. */
    public function ensure(CloudDocumentConnection $connection): void {
        if ($connection->provider !== CloudIntakeProvider::Google || trim((string) $connection->external_account_id) === '') {
            return;
        }

        $expiresAt = $connection->subscription_expires_at;
        if (trim((string) $connection->subscription_id) !== ''
            && $expiresAt instanceof Carbon
            && $expiresAt->greaterThan(Carbon::now()->addMinutes(self::RENEW_THRESHOLD_MINUTES))) {
            return; // noch lange genug gültig
        }

        $client = new GoogleDriveClient($connection);

        // Alten Kanal zuerst beenden — sonst liefen bis zu zwei Kanäle
        // parallel und weckten die Verbindung doppelt.
        $this->stopExisting($connection, $client);

        $token = trim((string) $connection->webhook_secret);
        if ($token === '') {
            $token = Str::random(48);
        }

        $channelId = (string) Str::uuid();
        $created = $client->watchChanges($channelId, route('api.webhooks.google-drive'), $token, self::LIFETIME_SECONDS);

        $connection->forceFill([
            'subscription_id' => $created['id'],
            'subscription_resource_id' => $created['resourceId'],
            'subscription_expires_at' => $created['expiration'] !== null
                ? Carbon::instance($created['expiration'])
                : Carbon::now()->addSeconds(self::LIFETIME_SECONDS),
            'webhook_secret' => $token,
        ])->save();
    }

    /** Kanal abmelden (best effort, idempotent) — beim Trennen/Deaktivieren. */
    public function unsubscribe(CloudDocumentConnection $connection): void {
        if (trim((string) $connection->subscription_id) === '') {
            return;
        }

        try {
            $this->stopExisting($connection, new GoogleDriveClient($connection));
        } catch (Throwable) {
            // Ohne gültiges Token nicht abmeldbar — der Kanal verfällt binnen
            // 24 Stunden von selbst, verwaiste Signale laufen im Empfänger ins
            // Leere (Lookup über subscription_id schlägt fehl).
        }

        $connection->forceFill([
            'subscription_id' => null,
            'subscription_resource_id' => null,
            'subscription_expires_at' => null,
        ])->save();
    }

    /**
     * Alle fälligen Drive-Kanäle sicherstellen (Scheduler-Lauf).
     *
     * @return array{ensured: int, failed: int}
     */
    public function ensureAll(?int $organizationId = null): array {
        $ensured = 0;
        $failed = 0;

        $query = CloudDocumentConnection::query()
            ->withoutGlobalScopes()
            ->where('provider', CloudIntakeProvider::Google->value)
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
            } catch (Throwable $e) {
                $failed++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        return ['ensured' => $ensured, 'failed' => $failed];
    }

    /** Bestehenden Kanal beenden, sofern Kanal-ID UND resourceId bekannt sind. */
    private function stopExisting(CloudDocumentConnection $connection, GoogleDriveClient $client): void {
        $channelId = trim((string) $connection->subscription_id);
        $resourceId = trim((string) $connection->subscription_resource_id);
        if ($channelId === '' || $resourceId === '') {
            return; // ohne resourceId lehnt Google channels.stop ab
        }

        $client->stopChannel($channelId, $resourceId);
    }
}
