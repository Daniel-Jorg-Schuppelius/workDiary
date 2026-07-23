<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthEventSubscriber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Models\{AuditLog, User};
use App\Notifications\GenericEventNotification;
use App\Services\Security\{KnownDeviceService, SecurityEventLogger};
use Illuminate\Auth\Events\{Failed, Lockout, Login, Logout, PasswordReset, PasswordResetLinkSent};
use Illuminate\Support\Facades\{Log, Request};

/**
 * Persistiert Auth-Events ins Audit-Log (sofern ein User-Bezug existiert),
 * schreibt Fehlversuche/Lockouts in das fail2ban-taugliche Security-Log
 * (Feature 096, MVP-443) und erkennt Anmeldungen von neuen Geräten
 * (MVP-446). Beim Lockout wird der betroffene Nutzer benachrichtigt —
 * er könnte gerade angegriffen werden.
 */
class AuthEventSubscriber {
    public function __construct(
        private readonly SecurityEventLogger $security,
        private readonly KnownDeviceService $devices,
    ) {}

    public function handleLogin(Login $event): void {
        $this->logForUser($event->user, 'auth.login');

        if ($event->user instanceof User) {
            try {
                $this->devices->touch($event->user, Request::userAgent(), Request::ip());
            } catch (\Throwable $e) {
                // Geräte-Erkennung darf den Login nie brechen.
                Log::warning('auth.known_device_failed', ['error' => $e->getMessage()]);
            }
        }
    }

    public function handleLogout(Logout $event): void {
        $this->logForUser($event->user, 'auth.logout');
    }

    public function handlePasswordReset(PasswordReset $event): void {
        $this->logForUser($event->user, 'auth.password_reset');
    }

    public function handleResetLinkSent(PasswordResetLinkSent $event): void {
        $email = (string) ($event->credentials['email'] ?? 'unknown');
        $this->security->log(SecurityEventType::PasswordResetRequested, ['user' => $email]);
    }

    public function handleFailed(Failed $event): void {
        $email = (string) ($event->credentials['email'] ?? $event->credentials['username'] ?? 'unknown');

        $this->security->log(SecurityEventType::AuthFailed, [
            'user' => $email,
            'guard' => $event->guard,
            'ua' => substr((string) Request::userAgent(), 0, 120),
        ]);

        if ($event->user instanceof User) {
            $this->logForUser($event->user, 'auth.failed');
        }
    }

    public function handleLockout(Lockout $event): void {
        $email = (string) $event->request->input('email', $event->request->input('username', ''));

        $this->security->log(SecurityEventType::AuthLockout, [
            'user' => $email !== '' ? $email : 'unknown',
            'ua' => substr((string) Request::userAgent(), 0, 120),
        ]);

        // Betroffenen Nutzer informieren (MVP-446): sein Konto wird gerade
        // per Brute-Force attackiert — auch wenn der Angriff scheitert.
        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
            if ($user instanceof User) {
                try {
                    $user->notify(new GenericEventNotification(
                        NotificationEvent::SecurityLockout,
                        [
                            'title' => (string) __('notification.message.lockout_title'),
                            'title_key' => 'notification.message.lockout_title',
                            'message' => (string) __('notification.message.lockout_message'),
                            'message_key' => 'notification.message.lockout_message',
                            'url' => route('account.password.edit'),
                        ],
                        ['database', 'mail'],
                    ));
                } catch (\Throwable $e) {
                    Log::warning('auth.lockout_notify_failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
            PasswordResetLinkSent::class => 'handleResetLinkSent',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }

    private function logForUser(mixed $user, string $event): void {
        if (! $user instanceof User) {
            return;
        }

        try {
            AuditLog::query()->create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'event' => $event,
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'changes' => null,
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Audit-Fehler dürfen Login/Logout nie blockieren: Exception schlucken, nur ins Application-Log.
            Log::warning('auth.audit_failed', [
                'event' => $event,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
