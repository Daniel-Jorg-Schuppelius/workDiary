<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Persistiert Auth-Events ins Audit-Log (sofern ein User-Bezug existiert)
 * und protokolliert anonyme Events (Failed-Login, Lockout) im Application-Log.
 */
class AuthEventSubscriber {
    public function handleLogin(Login $event): void {
        $this->logForUser($event->user, 'auth.login');
    }

    public function handleLogout(Logout $event): void {
        $this->logForUser($event->user, 'auth.logout');
    }

    public function handlePasswordReset(PasswordReset $event): void {
        $this->logForUser($event->user, 'auth.password_reset');
    }

    public function handleFailed(Failed $event): void {
        $email = (string) ($event->credentials['email'] ?? $event->credentials['username'] ?? 'unknown');

        Log::warning('auth.failed', [
            'email' => $email,
            'ip' => Request::ip(),
            'ua' => substr((string) Request::userAgent(), 0, 255),
        ]);

        if ($event->user instanceof User) {
            $this->logForUser($event->user, 'auth.failed');
        }
    }

    public function handleLockout(Lockout $event): void {
        Log::warning('auth.lockout', [
            'ip' => Request::ip(),
            'ua' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }

    private function logForUser(mixed $user, string $event): void {
        if (! $user instanceof User) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
