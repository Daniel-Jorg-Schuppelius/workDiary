<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeviceApprovalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use App\Enums\Notification\NotificationEvent;
use App\Models\Auth\UserKnownDevice;
use App\Models\Platform\User;
use App\Services\Notification\NotificationDispatcher;
use CommonToolkit\Helper\Data\UserAgentHelper;
use Illuminate\Database\Eloquent\Builder;

/**
 * Freigabe von Geräten für die Anmeldung von außerhalb der Büronetze
 * (Feature 205, MVP-1655): Ein neues Gerät stellt eine Freigabeanfrage,
 * Org-Admins (Recht `auth.policy.manage`) geben frei oder lehnen ab.
 *
 * Die Zeilen liegen in `user_known_devices` neben den Fingerprints der
 * Meldung „neues Gerät“; sie tragen den Hash des Gerätecookies in
 * `device_hash` (und, wegen des Unique-Index, auch in `fingerprint`).
 */
final class DeviceApprovalService {
    /** Höchstens so viele offene Anfragen je Person (Sicherheitsprüfung Feature 205, N5). */
    public const int MAX_OPEN_REQUESTS = 3;

    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /** Gerätezeile der Person zu diesem Gerätecookie (freigegeben, abgelehnt oder angefragt). */
    public function find(User $user, string $deviceHash): ?UserKnownDevice {
        return UserKnownDevice::query()
            ->where('user_id', $user->id)
            ->where('device_hash', $deviceHash)
            ->first();
    }

    public function isApproved(User $user, string $deviceHash): bool {
        return $this->find($user, $deviceHash)?->approved_at !== null;
    }

    /**
     * Freigabeanfrage für dieses Gerät sicherstellen. Ein bekanntes Gerät —
     * freigegeben, abgelehnt oder schon angefragt — fragt nicht erneut; die
     * Org-Admins werden nur beim Anlegen benachrichtigt. Hat die Person schon
     * {@see MAX_OPEN_REQUESTS} offene Anfragen, entsteht keine weitere.
     */
    public function request(User $user, string $deviceHash, ?string $userAgent): ?UserKnownDevice {
        $known = $this->find($user, $deviceHash);
        if ($known instanceof UserKnownDevice) {
            $known->forceFill(['last_seen_at' => now()])->save();

            return $known;
        }

        if ($this->openRequests($user) >= self::MAX_OPEN_REQUESTS) {
            return null;
        }

        /** @var UserKnownDevice $device */
        $device = UserKnownDevice::query()->createOrFirst(
            ['user_id' => $user->id, 'fingerprint' => $deviceHash],
            [
                'device_hash' => $deviceHash,
                'label' => mb_substr(UserAgentHelper::shortLabel($userAgent), 0, 120),
                'last_seen_at' => now(),
                'approval_requested_at' => now(),
            ],
        );

        if ($device->wasRecentlyCreated) {
            $this->notifyApprovers($user, $device);
        }

        return $device;
    }

    public function openRequests(User $user): int {
        return UserKnownDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('device_hash')
            ->whereNull('approved_at')
            ->whereNull('rejected_at')
            ->count();
    }

    /**
     * Geräte der Organisation, die eine Freigabe tragen, anfragen oder abgelehnt sind.
     *
     * @return Builder<UserKnownDevice>
     */
    public function devicesOf(int $organizationId): Builder {
        return UserKnownDevice::query()
            ->whereNotNull('device_hash')
            ->whereHas('user', static fn (Builder $query) => $query->where('organization_id', $organizationId));
    }

    public function approve(UserKnownDevice $device, User $actor): void {
        $device->forceFill(['approved_at' => now(), 'approved_by' => $actor->id, 'rejected_at' => null, 'rejected_by' => null])->save();
        $device->user?->audit('login_policy.device_approved', ['device_id' => $device->id, 'device' => $device->label]);
    }

    /**
     * Anfrage ablehnen oder Freigabe entziehen: das Gerät bleibt als
     * abgelehnt stehen und fragt nicht erneut an.
     */
    public function reject(UserKnownDevice $device, User $actor): void {
        $event = $device->approved_at !== null ? 'login_policy.device_revoked' : 'login_policy.device_rejected';
        $device->forceFill(['approved_at' => null, 'approved_by' => null, 'rejected_at' => now(), 'rejected_by' => $actor->id])->save();
        $device->user?->audit($event, ['device_id' => $device->id, 'device' => $device->label]);
    }

    /** Eintrag eines abgelehnten Geräts löschen: die nächste Anmeldung darf wieder anfragen. */
    public function forget(UserKnownDevice $device): void {
        $device->user?->audit('login_policy.device_forgotten', ['device_id' => $device->id, 'device' => $device->label]);
        $device->delete();
    }

    /**
     * Über den Dispatcher: Vorgabe sind die Org-Admins, die Organisation kann
     * die Regel auf andere Empfänger mit dem Freigaberecht legen. Fehler beim
     * Versand fängt der Dispatcher ab — die Anfrage bleibt bestehen.
     */
    private function notifyApprovers(User $user, UserKnownDevice $device): void {
        $params = ['name' => $user->name, 'device' => $device->label];

        $this->notifications->notify(NotificationEvent::AuthDeviceApprovalRequested, $device, $user, [
            'title' => (string) __('login_policy.notification.device_title', $params),
            'title_key' => 'login_policy.notification.device_title',
            'title_params' => $params,
            'message' => (string) __('login_policy.notification.device_message', $params),
            'message_key' => 'login_policy.notification.device_message',
            'message_params' => $params,
            'url' => route('admin.login-policy.index'),
        ]);
    }
}
