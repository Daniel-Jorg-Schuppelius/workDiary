<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlySubscriptionManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Services;

use App\Models\{CalendlyConnection, CalendlyWebhookSubscription};
use App\Plugins\Calendly\Api\CalendlyClient;
use Illuminate\Support\Str;

/**
 * Verwaltet die Calendly-Webhook-Subscription je Verbindung (Feature 095): legt
 * sie mit einem opaken `url_token` (Callback-URL) und einem `signing_key`
 * (HMAC-Secret) an. Der Token schlägt später Org + signing_key in O(1) nach —
 * die Organisation wird NIE aus dem Payload geraten. Heilung: fehlt die aktive
 * Subscription (z. B. von Calendly deaktiviert), wird sie neu angelegt.
 */
class CalendlySubscriptionManager {
    /** @var list<string> */
    private const EVENTS = ['invitee.created', 'invitee.canceled'];

    /** Stellt sicher, dass eine aktive Subscription existiert (legt sonst an). */
    public function ensure(CalendlyConnection $connection): ?CalendlyWebhookSubscription {
        $existing = CalendlyWebhookSubscription::query()
            ->where('calendly_connection_id', $connection->id)
            ->where('status', CalendlyWebhookSubscription::STATUS_ACTIVE)
            ->first();

        if ($existing instanceof CalendlyWebhookSubscription) {
            return $existing;
        }

        return $this->create($connection);
    }

    /** Legt eine neue org-weite Subscription bei Calendly + lokal an. */
    public function create(CalendlyConnection $connection): ?CalendlyWebhookSubscription {
        $organizationUri = (string) $connection->calendly_organization_uri;
        if ($organizationUri === '') {
            return null;
        }

        $token = Str::random(48);
        $signingKey = bin2hex(random_bytes(32));

        $resource = (new CalendlyClient($connection))->createWebhookSubscription([
            'url' => route('api.webhooks.calendly', ['token' => $token]),
            'events' => self::EVENTS,
            'organization' => $organizationUri,
            'scope' => CalendlyWebhookSubscription::SCOPE_ORGANIZATION,
            'signing_key' => $signingKey,
        ]);

        if ($resource === null) {
            return null;
        }

        // Falls Calendly einen eigenen signing_key zurückgibt, gewinnt dieser.
        if (is_string($resource['signing_key'] ?? null) && $resource['signing_key'] !== '') {
            $signingKey = $resource['signing_key'];
        }

        return CalendlyWebhookSubscription::create([
            'organization_id' => $connection->organization_id,
            'calendly_connection_id' => $connection->id,
            'url_token' => $token,
            'signing_key' => $signingKey,
            'calendly_subscription_uri' => is_string($resource['uri'] ?? null) ? $resource['uri'] : null,
            'scope' => CalendlyWebhookSubscription::SCOPE_ORGANIZATION,
            'events' => self::EVENTS,
            'status' => CalendlyWebhookSubscription::STATUS_ACTIVE,
        ]);
    }

    /** Meldet die Subscription bei Calendly ab und markiert sie lokal als deaktiviert. */
    public function remove(CalendlyConnection $connection): void {
        $subscriptions = CalendlyWebhookSubscription::query()
            ->where('calendly_connection_id', $connection->id)
            ->where('status', CalendlyWebhookSubscription::STATUS_ACTIVE)
            ->get();

        $client = new CalendlyClient($connection);
        foreach ($subscriptions as $subscription) {
            $uri = (string) $subscription->calendly_subscription_uri;
            if ($uri !== '') {
                $client->deleteWebhookSubscription($uri);
            }
            $subscription->forceFill(['status' => CalendlyWebhookSubscription::STATUS_DISABLED])->save();
        }
    }
}
