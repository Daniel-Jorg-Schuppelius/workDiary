<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GraphSubscriptionClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Api;

/**
 * Graph-Client, der Change-Notification-Subscriptions verwalten kann
 * (Feature 102, Folgeausbau) — Implementierung liefert der Trait
 * {@see Concerns\ManagesGraphSubscriptions}; das Interface erlaubt dem
 * {@see \App\Plugins\Msgraph\Services\MsgraphSubscriptionService} eine
 * generische Ensure-/Renew-Logik über alle Verbindungsarten.
 */
interface GraphSubscriptionClient {
    /**
     * Legt die Subscription an; Rückgabe = Graph-ID + Ablauf.
     *
     * @return array{id: string, expires_at: string}
     */
    public function createSubscription(string $notificationUrl, string $resource, string $clientState, \DateTimeInterface $expiresAt, string $changeType = 'updated'): array;

    /** Verlängert die Subscription; false = 404 (abgelaufen/gelöscht → neu anlegen). */
    public function renewSubscription(string $subscriptionId, \DateTimeInterface $expiresAt): bool;

    /** Meldet die Subscription ab (404 = bereits weg, idempotent ok). */
    public function deleteSubscription(string $subscriptionId): void;
}
