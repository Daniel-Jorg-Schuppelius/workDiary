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
use App\Models\{EmailConnection, MsgraphConnection, MsgraphMailConnection, MsgraphTaskConnection, MsgraphTaskListLink};
use App\Plugins\Msgraph\Api\{GraphSubscriptionClient, MsgraphCalendarClient, MsgraphIntakeClient, MsgraphMailClient, MsgraphTodoClient};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Carbon, Str};

/**
 * Sender-Seite der Graph-Change-Notifications (MS365-Plan §8 + Feature 102
 * Folgeausbau): legt Subscriptions an, erneuert sie rechtzeitig und meldet
 * sie beim Trennen ab — für vier Verbindungsarten:
 *
 *  - Dokumenteingang (Drive-Root, < 30 Tage) → `api.webhooks.msgraph-intake`
 *  - Zwei-Wege-Kalender (`/me/events`, < 7 Tage) → `api.webhooks.msgraph`
 *  - To-Do-Listen-Links (nur importierende, < 3 Tage) → `api.webhooks.msgraph`
 *  - Graph-Postfächer (Inbox, < 7 Tage) → `api.webhooks.msgraph`
 *
 * Der Webhook bleibt reines Aufwecksignal — die Wahrheit holt weiterhin der
 * Delta-/Sync-Lauf. clientState = `webhook_secret` des Trägers (encrypted
 * at-rest); der Empfänger vergleicht in Konstantzeit
 * ({@see \App\Plugins\Support\WebhookSignature}). Beim Trennen einer
 * Verbindung wird bewusst NICHT überall abgemeldet: Graph räumt die
 * kurzlebigen Subscriptions (≤ 7 Tage) selbst ab, verwaiste Notifications
 * laufen im Empfänger ins Leere (Lookup schlägt fehl).
 */
class MsgraphSubscriptionService {
    /** Laufzeit Drive-Subscriptions (Graph-Limit driveItem: < 30 Tage). */
    private const LIFETIME_DRIVE_DAYS = 29;

    /** Laufzeit Outlook-Subscriptions (Events/Messages: < 7 Tage). */
    private const LIFETIME_OUTLOOK_DAYS = 6;

    /** Laufzeit To-Do-Subscriptions (todoTask: < 3 Tage). */
    private const LIFETIME_TODO_DAYS = 2;

    /** Erneuern, wenn weniger als so viele Tage Restlaufzeit bleiben. */
    private const RENEW_THRESHOLD_DRIVE_DAYS = 3;

    private const RENEW_THRESHOLD_OUTLOOK_DAYS = 2;

    private const RENEW_THRESHOLD_TODO_DAYS = 1;

    /**
     * Subscription der Dokumenteingangs-Verbindung sicherstellen.
     * Wirft bei API-Fehlern — Aufrufer entscheiden über best-effort.
     */
    public function ensure(CloudDocumentConnection $connection): void {
        if ($connection->provider !== CloudIntakeProvider::Microsoft || trim((string) $connection->container_id) === '') {
            return;
        }

        $this->ensureSubscription(
            $connection,
            new MsgraphIntakeClient($connection),
            '/drives/' . $connection->container_id . '/root',
            route('api.webhooks.msgraph-intake'),
            self::LIFETIME_DRIVE_DAYS,
            self::RENEW_THRESHOLD_DRIVE_DAYS,
            'updated', // driveItem erlaubt nur 'updated'
        );
    }

    /** Zwei-Wege-Kalender: weckt den Delta-Rückimport (nur two_way-Opt-in). */
    public function ensureCalendar(MsgraphConnection $connection): void {
        if (! $connection->two_way || ! $connection->isActive()) {
            return;
        }

        $calendarId = trim((string) $connection->calendar_id);
        $this->ensureSubscription(
            $connection,
            new MsgraphCalendarClient($connection),
            $calendarId !== '' ? '/me/calendars/' . $calendarId . '/events' : '/me/events',
            route('api.webhooks.msgraph'),
            self::LIFETIME_OUTLOOK_DAYS,
            self::RENEW_THRESHOLD_OUTLOOK_DAYS,
            'created,updated,deleted',
        );
    }

    /** To-Do-Liste: weckt den Sync des Links (nur importierende Links). */
    public function ensureTodoLink(MsgraphTaskListLink $link, MsgraphTaskConnection $connection): void {
        if ($link->status !== MsgraphTaskListLink::STATUS_ACTIVE || ! $link->importsFromTodo() || ! $connection->isActive()) {
            return; // reine Export-Links weckt der Observer, kein Webhook nötig
        }

        $this->ensureSubscription(
            $link,
            new MsgraphTodoClient($connection),
            '/me/todo/lists/' . $link->todo_list_id . '/tasks',
            route('api.webhooks.msgraph'),
            self::LIFETIME_TODO_DAYS,
            self::RENEW_THRESHOLD_TODO_DAYS,
            'created,updated,deleted',
        );
    }

    /**
     * Graph-Postfach (transport=msgraph): weckt den Mail-Abruf. Subscribiert
     * bewusst die Inbox — Postfächer mit abweichendem Abruf-Ordner heilt das
     * 5-Minuten-Polling (`mail:poll`).
     */
    public function ensureMailbox(EmailConnection $connection): void {
        if (! $connection->isMsgraph() || ! $connection->isActive()) {
            return;
        }

        $mail = MsgraphMailConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->first();
        if (! $mail instanceof MsgraphMailConnection || ! $mail->isActive()) {
            return; // ohne Graph-Mail-Verbindung kein Token — Polling bleibt
        }

        $this->ensureSubscription(
            $connection,
            new MsgraphMailClient($mail),
            "/me/mailFolders('inbox')/messages",
            route('api.webhooks.msgraph'),
            self::LIFETIME_OUTLOOK_DAYS,
            self::RENEW_THRESHOLD_OUTLOOK_DAYS,
            'created',
        );
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
     * Alle fälligen Subscriptions sicherstellen (Scheduler-Lauf): Dokument-
     * eingang, Zwei-Wege-Kalender, importierende To-Do-Links, Graph-Postfächer.
     *
     * @return array{ensured: int, failed: int}
     */
    public function ensureAll(?int $organizationId = null): array {
        $ensured = 0;
        $failed = 0;

        // ── Dokumenteingang (Drive-Root, MVP-354) ───────────────────────
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

        // ── Zwei-Wege-Kalender (Feature 102, C3) ────────────────────────
        $calendars = MsgraphConnection::query()
            ->withoutGlobalScopes()
            ->where('two_way', true);
        if ($organizationId !== null) {
            $calendars->where('organization_id', $organizationId);
        }

        foreach ($calendars->get() as $connection) {
            if (! $connection->isActive()) {
                continue;
            }
            try {
                $this->ensureCalendar($connection);
                $ensured++;
            } catch (\Throwable $e) {
                $failed++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        // ── To-Do-Listen-Links (Feature 102, Schnitt E) ─────────────────
        $links = MsgraphTaskListLink::query()
            ->withoutGlobalScopes()
            ->where('status', MsgraphTaskListLink::STATUS_ACTIVE);
        if ($organizationId !== null) {
            $links->where('organization_id', $organizationId);
        }

        /** @var array<int, MsgraphTaskConnection|null> $taskConnections */
        $taskConnections = [];
        foreach ($links->get() as $link) {
            if (! $link->importsFromTodo()) {
                continue;
            }
            $orgId = (int) $link->organization_id;
            if (! array_key_exists($orgId, $taskConnections)) {
                $taskConnections[$orgId] = MsgraphTaskConnection::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->first();
            }
            $connection = $taskConnections[$orgId];
            if (! $connection instanceof MsgraphTaskConnection || ! $connection->isActive()) {
                continue;
            }
            try {
                $this->ensureTodoLink($link, $connection);
                $ensured++;
            } catch (\Throwable $e) {
                $failed++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        // ── Graph-Postfächer (Feature 102, Mail-Eingang) ────────────────
        $mailboxes = EmailConnection::query()
            ->withoutGlobalScopes()
            ->where('transport', EmailConnection::TRANSPORT_MSGRAPH);
        if ($organizationId !== null) {
            $mailboxes->where('organization_id', $organizationId);
        }

        foreach ($mailboxes->get() as $connection) {
            if (! $connection->isActive()) {
                continue;
            }
            try {
                $this->ensureMailbox($connection);
                $ensured++;
            } catch (\Throwable $e) {
                $failed++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        return ['ensured' => $ensured, 'failed' => $failed];
    }

    /**
     * Gemeinsamer Ensure-Kern: vorhandene Subscription verlängern (solange
     * das Graph-seitige Original existiert), sonst mit stabilem clientState
     * neu anlegen und die Felder am Träger fortschreiben.
     */
    private function ensureSubscription(Model $holder, GraphSubscriptionClient $client, string $resource, string $notificationUrl, int $lifetimeDays, int $renewThresholdDays, string $changeType): void {
        $expiresAt = Carbon::now()->addDays($lifetimeDays);

        $subscriptionId = trim((string) $holder->getAttribute('subscription_id'));
        if ($subscriptionId !== '') {
            $current = $holder->getAttribute('subscription_expires_at');
            if ($current instanceof Carbon && $current->greaterThan(Carbon::now()->addDays($renewThresholdDays))) {
                return; // noch lange gültig
            }
            if ($client->renewSubscription($subscriptionId, $expiresAt)) {
                $holder->forceFill(['subscription_expires_at' => $expiresAt])->save();

                return;
            }
            // 404: abgelaufen/gelöscht → unten neu anlegen.
        }

        $clientState = trim((string) $holder->getAttribute('webhook_secret'));
        if ($clientState === '') {
            $clientState = Str::random(48);
        }

        $created = $client->createSubscription($notificationUrl, $resource, $clientState, $expiresAt, $changeType);

        $holder->forceFill([
            'subscription_id' => $created['id'],
            'subscription_expires_at' => $created['expires_at'] !== '' ? Carbon::parse($created['expires_at']) : $expiresAt,
            'webhook_secret' => $clientState,
        ])->save();
    }
}
