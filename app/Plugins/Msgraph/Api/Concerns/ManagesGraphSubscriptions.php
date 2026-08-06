<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesGraphSubscriptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Api\Concerns;

use RuntimeException;

/**
 * Graph-Change-Notification-Subscriptions (MS365-Plan §8, Feature 102
 * Folgeausbau): Anlage/Erneuerung/Abmeldung über `/subscriptions` — vom
 * Intake-Client (MVP-354) hierher extrahiert, damit Kalender-, To-Do- und
 * Mail-Client dieselbe Sender-Seite nutzen. Erwartet `$this->api`
 * (PluginApiClient mit gesetzter Auth) und `$this->base` im nutzenden Client.
 * Laufzeit-Limits je Ressource (driveItem < 30 Tage, Outlook < 7 Tage,
 * To Do < 3 Tage) verantwortet der Aufrufer.
 */
trait ManagesGraphSubscriptions {
    /**
     * Legt die Subscription an; Rückgabe = Graph-ID + Ablauf.
     * `changeType`: driveItem erlaubt nur 'updated'; Outlook/To Do auch
     * 'created,updated,deleted' (kommasepariert).
     *
     * @return array{id: string, expires_at: string}
     */
    public function createSubscription(string $notificationUrl, string $resource, string $clientState, \DateTimeInterface $expiresAt, string $changeType = 'updated'): array {
        $response = $this->api->postJson($this->base . '/subscriptions', [
            'changeType' => $changeType,
            'notificationUrl' => $notificationUrl,
            'resource' => $resource,
            'clientState' => $clientState,
            'expirationDateTime' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Graph POST /subscriptions fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return [
            'id' => (string) $response->json('id', ''),
            'expires_at' => (string) $response->json('expirationDateTime', ''),
        ];
    }

    /** Verlängert die Subscription; false = 404 (abgelaufen/gelöscht → neu anlegen). */
    public function renewSubscription(string $subscriptionId, \DateTimeInterface $expiresAt): bool {
        $response = $this->api->requestResponse('patch', $this->base . '/subscriptions/' . rawurlencode($subscriptionId), [
            'json' => ['expirationDateTime' => $expiresAt->format('Y-m-d\TH:i:s\Z')],
        ]);
        if ($response->status() === 404) {
            return false;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph PATCH /subscriptions fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return true;
    }

    /** Meldet die Subscription ab (404 = bereits weg, idempotent ok). */
    public function deleteSubscription(string $subscriptionId): void {
        $response = $this->api->deleteResponse($this->base . '/subscriptions/' . rawurlencode($subscriptionId));
        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Graph DELETE /subscriptions fehlgeschlagen (HTTP ' . $response->status() . ').');
        }
    }
}
