<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Notification;

use App\Enums\Integration\WebhookEvent;
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Jobs\Notification\ChatWebhookDeliveryJob;
use App\Models\{ChatWebhook, User};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Notifications\GenericEventNotification;
use App\Services\Integration\WebhookDispatchService;
use App\Services\WebPushService;
use App\Support\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Zentraler Versandweg für Benachrichtigungen (MVP-018).
 *
 * Löst die Organisations-Regel ({@see NotificationRule}) für ein Ereignis auf,
 * ermittelt die Empfänger (betroffene Person / Rollen / feste User), respektiert
 * Per-User-Präferenzen (mail_enabled, push_enabled, Ruhezeit) und versendet über
 * Database (In-App), Mail und optional WebPush.
 *
 * Synchron aufrufende Services dürfen durch Benachrichtigungs-Fehler nie
 * scheitern: notify() fängt außerhalb von Tests alle Exceptions und loggt sie.
 */
class NotificationDispatcher {
    public function __construct(private readonly WebPushService $webPush) {}

    /**
     * Versendet ein Ereignis gemäß Organisations-Regel.
     *
     * @param  Model  $subject  fachliches Subjekt (OpenIssue, Document, …)
     * @param  User|null  $affected  betroffene Person (Empfänger bei notify_affected)
     * @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload
     * @param  string  $stage  initial|escalation (Dedup-Stufe)
     * @param  bool  $dedup  true = pro (Org, Event, Subjekt, Stufe) nur einmal versenden (Scanner)
     * @return int Anzahl benachrichtigter Empfänger
     */
    public function notify(
        NotificationEvent $event,
        Model $subject,
        ?User $affected,
        array $payload,
        string $stage = NotificationDispatchLog::STAGE_INITIAL,
        bool $dedup = false,
    ): int {
        // Additiver Webhook-Hook (Feature 008): jedes real gefeuerte
        // Ereignis, das eine WebhookEvent-Entsprechung hat, wird an die
        // aktiven, abonnierten Endpunkte der Organisation gefächert — ohne
        // Einfluss auf die Benachrichtigungs-Geschäftslogik. Nur die initiale
        // Stufe wird publiziert (Eskalationen sind rein interne Vorgänge).
        if ($stage === NotificationDispatchLog::STAGE_INITIAL) {
            $this->publishWebhook($event, $subject, $payload);
            $this->publishChatChannels($event, $subject, $payload);
        }

        try {
            return $this->dispatch($event, $subject, $affected, $payload, $stage, $dedup);
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            Log::warning('notification: dispatch failed', [
                'event' => $event->value,
                'subject' => $subject::class . '#' . $subject->getKey(),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Eskalation light (Scanner): wenn die Erst-Benachrichtigung länger als
     * escalate_after_hours zurückliegt und das Subjekt weiterhin unerledigt
     * ist, zusätzlich (einmalig) an die Eskalations-Rolle senden.
     *
     * @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload
     */
    public function escalateIfDue(NotificationEvent $event, Model $subject, array $payload): int {
        $organizationId = $this->organizationIdOf($subject, null);
        if ($organizationId === null) {
            return 0;
        }

        $rule = NotificationRule::resolveFor($organizationId, $event);
        if (! $rule->enabled || ! $rule->escalation_enabled
            || $rule->escalate_after_hours === null || $rule->escalation_role === null) {
            return 0;
        }

        $initial = NotificationDispatchLog::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('event', $event->value)
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('stage', NotificationDispatchLog::STAGE_INITIAL)
            ->first();

        if ($initial === null || $initial->created_at === null
            || $initial->created_at->gt(Carbon::now()->subHours((int) $rule->escalate_after_hours))) {
            return 0;
        }

        return $this->notify($event, $subject, null, $payload, NotificationDispatchLog::STAGE_ESCALATION, true);
    }

    /** @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload */
    private function dispatch(
        NotificationEvent $event,
        Model $subject,
        ?User $affected,
        array $payload,
        string $stage,
        bool $dedup,
    ): int {
        $organizationId = $this->organizationIdOf($subject, $affected);
        if ($organizationId === null) {
            return 0;
        }

        $rule = NotificationRule::resolveFor($organizationId, $event);
        if (! $rule->enabled) {
            return 0;
        }

        $recipients = $stage === NotificationDispatchLog::STAGE_ESCALATION
            ? $this->roleUsers($organizationId, array_filter([(string) $rule->escalation_role]))
            : $this->resolveRecipients($rule, $organizationId, $affected);

        if ($recipients->isEmpty()) {
            return 0;
        }

        if ($dedup && ! $this->claimDispatch($organizationId, $event, $subject, $stage, $recipients->count())) {
            return 0;
        }

        $sent = 0;
        foreach ($recipients as $user) {
            if ($this->deliverTo($user, $rule, $event, $payload, $stage)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Empfänger gemäß Regel: betroffene Person + Rollen-Inhaber + feste User
     * (dedupliziert, nur Mitglieder derselben Organisation).
     *
     * @return Collection<int, User>
     */
    private function resolveRecipients(NotificationRule $rule, int $organizationId, ?User $affected): Collection {
        $recipients = collect();

        if ($rule->notify_affected && $affected !== null && (int) $affected->organization_id === $organizationId) {
            $recipients->push($affected);
        }

        $roles = array_values(array_filter((array) $rule->recipient_roles));
        if ($roles !== []) {
            $recipients = $recipients->merge($this->roleUsers($organizationId, $roles));
        }

        $userIds = array_values(array_filter(array_map('intval', (array) $rule->recipient_user_ids)));
        if ($userIds !== []) {
            $recipients = $recipients->merge(
                User::query()
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $userIds)
                    ->get()
            );
        }

        return $recipients->unique(fn(User $u): int => (int) $u->id)->values();
    }

    /**
     * Inhaber einer (Org-)Rolle. Spatie-Rollen sind team-scoped, daher wird
     * der Registrar-Kontext temporär auf die Ziel-Organisation gesetzt
     * (wichtig für Konsolen-/Scanner-Läufe ohne HTTP-Mandantenkontext).
     *
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    private function roleUsers(int $organizationId, array $roles): Collection {
        if ($roles === []) {
            return collect();
        }

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($organizationId);

        try {
            return User::query()
                ->where('organization_id', $organizationId)
                // Guard explizit pinnen: läuft der Request über den
                // customer-Guard (Portal, z. B. Foto-Beanstandung), würde
                // Spatie sonst die Org-Rollen im Guard `customer` suchen
                // und mit RoleDoesNotExist abbrechen.
                ->role($roles, 'web')
                ->get();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Dedup-Claim über den Unique-Key von notification_dispatch_log:
     * genau ein Prozess gewinnt; alle weiteren (auch parallele) verlieren.
     */
    private function claimDispatch(int $organizationId, NotificationEvent $event, Model $subject, string $stage, int $recipientCount): bool {
        $exists = NotificationDispatchLog::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('event', $event->value)
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('stage', $stage)
            ->exists();

        if ($exists) {
            return false;
        }

        try {
            NotificationDispatchLog::query()->create([
                'organization_id' => $organizationId,
                'event' => $event->value,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'stage' => $stage,
                'recipient_count' => $recipientCount,
            ]);
        } catch (Throwable) {
            // Unique-Key verletzt → ein paralleler Lauf hat bereits versendet.
            return false;
        }

        return true;
    }

    /**
     * Versand an einen Empfänger über die laut Regel + Präferenzen erlaubten Kanäle.
     *
     * @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload
     */
    private function deliverTo(User $user, NotificationRule $rule, NotificationEvent $event, array $payload, string $stage): bool {
        $prefs = (array) $user->getPreference('notifications', []);
        $quiet = $this->isQuietNow($user, $prefs);

        $channels = [];
        if ($rule->usesChannel(NotificationChannel::InApp)) {
            // In-App sammelt immer — unabhängig von Ruhezeit.
            $channels[] = 'database';
        }
        if ($rule->usesChannel(NotificationChannel::Mail) && ! $quiet
            && filter_var(data_get($prefs, 'mail_enabled', true), FILTER_VALIDATE_BOOL)) {
            $channels[] = 'mail';
        }

        $delivered = false;
        if ($channels !== []) {
            $user->notify(new GenericEventNotification($event, $payload, $channels, $stage));
            $delivered = true;
        }

        if ($rule->usesChannel(NotificationChannel::Push) && ! $quiet
            && filter_var(data_get($prefs, 'push_enabled', true), FILTER_VALIDATE_BOOL)) {
            $truncate = (int) Setting::get('notifications.push.body_truncate', 120);
            $sent = $this->webPush->sendToUser($user, [
                'title' => $payload['title'],
                'body' => mb_substr((string) ($payload['message'] ?? $event->label()), 0, $truncate),
                'url' => (string) ($payload['url'] ?? ''),
                'tag' => 'notification-' . $event->value,
            ]);
            $delivered = $delivered || $sent > 0;
        }

        return $delivered;
    }

    /**
     * Ruhezeit des Empfängers (preferences.notifications.quiet_from/quiet_to,
     * Format H:i, in der Anzeige-Zeitzone des Users). Unterstützt auch
     * Über-Nacht-Fenster (z. B. 22:00–06:00). Gilt nur für Mail/Push.
     *
     * @param  array<string, mixed>  $prefs
     */
    private function isQuietNow(User $user, array $prefs): bool {
        $from = (string) data_get($prefs, 'quiet_from', '');
        $to = (string) data_get($prefs, 'quiet_to', '');
        if ($from === '' || $to === '' || $from === $to) {
            return false;
        }

        $tz = $user->timezone ?: config('app.timezone', 'UTC');
        $now = Carbon::now($tz)->format('H:i');

        if ($from < $to) {
            return $now >= $from && $now < $to;
        }

        // Über-Nacht-Fenster (from > to), z. B. 22:00–06:00.
        return $now >= $from || $now < $to;
    }

    /**
     * Veröffentlicht das Ereignis als ausgehenden Webhook, sofern es eine
     * {@see WebhookEvent}-Entsprechung hat. Vollständig gekapselt: ein Fehler
     * hier darf die Benachrichtigung/Geschäftslogik nie scheitern lassen.
     *
     * @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload
     */
    private function publishWebhook(NotificationEvent $event, Model $subject, array $payload): void {
        try {
            $webhookEvent = WebhookEvent::forSource($event);
            if ($webhookEvent === null) {
                return;
            }

            $organizationId = $this->organizationIdOf($subject, null);
            if ($organizationId === null) {
                return;
            }

            // Minimaler, dokumentierter Payload: fachliches Subjekt (Typ + Sqid-
            // fähige ID) und ein knapper Titel. Bewusst arm an personenbezogenen
            // Daten — Empfänger reichern bei Bedarf über die REST-API an.
            $data = [
                'subject_type' => class_basename($subject),
                'subject_id' => $subject->getKey(),
                'title' => $payload['title'],
            ];
            if (isset($payload['url']) && $payload['url'] !== '') {
                $data['url'] = (string) $payload['url'];
            }

            app(WebhookDispatchService::class)->publish($webhookEvent, $organizationId, $data);
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            Log::warning('webhook: hook failed', [
                'event' => $event->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Additiver Team-Messenger-Hook (Feature 056, MVP-119): fächert das Ereignis
     * an die aktiven, ausgehenden Chat-Kanäle (Teams/Mattermost) der Organisation
     * — org-weit (eine Kanal-URL je Kanal), nicht empfängerbezogen. Ausgewählt
     * über dieselbe Ereignis→Kanal-Matrix ({@see NotificationRule}). Zustellung
     * erfolgt asynchron mit Retry ({@see ChatWebhookDeliveryJob}).
     *
     * @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload
     */
    private function publishChatChannels(NotificationEvent $event, Model $subject, array $payload): void {
        try {
            $organizationId = $this->organizationIdOf($subject, null);
            if ($organizationId === null) {
                return;
            }

            $rule = NotificationRule::resolveFor($organizationId, $event);
            if (! $rule->enabled) {
                return;
            }

            $webhooks = ChatWebhook::query()
                ->where('organization_id', $organizationId)
                ->where('active', true)
                ->get();

            foreach ($webhooks as $webhook) {
                if (! $webhook->isActive() || ! $rule->usesChannel($webhook->channel())) {
                    continue;
                }

                ChatWebhookDeliveryJob::dispatch((int) $webhook->id, (string) $event->label(), [
                    'title' => (string) $payload['title'],
                    'message' => isset($payload['message']) ? (string) $payload['message'] : null,
                    'url' => isset($payload['url']) && $payload['url'] !== '' ? (string) $payload['url'] : null,
                ]);
            }
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            Log::warning('chat: hook failed', [
                'event' => $event->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function organizationIdOf(Model $subject, ?User $affected): ?int {
        $orgId = $subject->getAttribute('organization_id') ?: $affected?->organization_id;

        return $orgId !== null ? (int) $orgId : null;
    }
}
