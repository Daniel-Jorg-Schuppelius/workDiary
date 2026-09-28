<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountTakeoverResponder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Auth\TwoFactorType;
use App\Enums\Security\SecurityEventType;
use App\Models\Audit\AuditLog;
use App\Models\Auth\{SecurityEvent, UserKnownDevice};
use App\Models\Platform\User;
use App\Services\Auth\{PasswordResetLinkSender, UserSessionInvalidator};
use App\Support\MorphMap;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reaktion auf eine bestätigte Kontoübernahme (Feature 097, MVP-1008).
 *
 * Ein erzwungener Passwortwechsel beim nächsten Anmelden reicht nicht: Wer das
 * Passwort kennt, käme dem Nutzer zuvor. Deshalb wird das Passwort durch ein
 * unbekanntes ersetzt und nur der Link an die hinterlegte Adresse führt zurück.
 * Passkeys fallen mit, weil sie ohne Passwort anmelden.
 */
final class AccountTakeoverResponder {
    public const SOURCE_SECURITY_EVENT = 'security_event';
    public const SOURCE_ORGANIZATION_ADMIN = 'organization_admin';
    public const SOURCE_SELF = 'self';

    public function __construct(
        private readonly UserSessionInvalidator $sessions,
        private readonly PasswordResetLinkSender $resetLinks,
        private readonly SecurityEventLogger $security,
    ) {}

    public function secure(User $user, ?User $actor, string $source, ?SecurityEvent $event = null): void {
        if ($user->customer_id !== null || trim((string) $user->email) === '') {
            throw ValidationException::withMessages(['user' => (string) __('security.account_secure.error.not_securable')]);
        }

        $passkeys = DB::transaction(function () use ($user): int {
            $user->forceFill([
                'password' => Hash::make(Str::random(64)),
                'is_new_system' => true,
                'must_change_password' => true,
            ])->save();
            UserKnownDevice::query()->where('user_id', $user->id)->delete();

            return $user->twoFactorCredentials()->where('type', TwoFactorType::Webauthn->value)->delete();
        });
        $this->sessions->invalidateAll($user);
        $this->resetLinks->send($user);

        $this->security->log(SecurityEventType::AccountSecured, [
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'source' => $source,
            'actor_id' => $actor?->id,
            'event_id' => $event?->id,
            'passkeys' => $passkeys,
        ]);
        AuditLog::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $actor?->id,
            'event' => 'user.account_secured',
            'auditable_type' => MorphMap::stableKey(User::class),
            'auditable_id' => $user->id,
            'changes' => ['source' => $source, 'security_event_id' => $event?->id, 'passkeys_removed' => $passkeys],
        ]);
    }
}
