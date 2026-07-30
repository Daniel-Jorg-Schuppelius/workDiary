<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnownDeviceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Notification\NotificationEvent;
use App\Models\{User, UserKnownDevice};
use App\Notifications\GenericEventNotification;
use CommonToolkit\Helper\Data\{CryptoHelper, UserAgentHelper};
use CommonToolkit\Helper\Geo\IpLocationHelper;

/**
 * „Anmeldung von neuem Gerät/Standort" (Feature 096, MVP-446): Fingerprint
 * aus UA-Familie (ohne Versionen — Browser-Updates alarmieren nicht) plus
 * grobem Geo-Land; Erstkontakt benachrichtigt den Nutzer per Mail + In-App.
 * Fängt Kontoübernahmen mit korrektem Passwort, die fail2ban nie sieht.
 * Ohne .mmdb degradiert das Geo-Merkmal still auf „unbekannt".
 */
class KnownDeviceService {
    /** users.preferences-Schlüssel für das Opt-out (Default: aktiv). */
    public const PREFERENCE_KEY = 'security_new_device_alert';

    public function touch(User $user, ?string $userAgent, ?string $ip): void {
        $label = UserAgentHelper::shortLabel($userAgent);
        $country = $this->country($ip);
        $fingerprint = CryptoHelper::hash($user->id . '|' . $label . '|' . ($country ?? '-'));
        // Grobe Koordinaten als Referenz der Impossible-Travel-Erkennung
        // (MVP-449); ohne .mmdb bleiben sie null und die Prüfung ruht.
        $coordinates = IpLocationHelper::coordinates($ip);

        $known = UserKnownDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($known !== null) {
            $known->forceFill(array_filter([
                'last_seen_at' => now(),
                'latitude' => $coordinates['lat'] ?? null,
                'longitude' => $coordinates['lon'] ?? null,
            ], static fn($value): bool => $value !== null))->save();

            return;
        }

        // Erst-Anmeldung überhaupt = Enrollment, kein Alarm.
        $isFirstDevice = ! UserKnownDevice::query()->where('user_id', $user->id)->exists();

        UserKnownDevice::query()->create([
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'label' => $label,
            'country' => $country,
            'latitude' => $coordinates['lat'] ?? null,
            'longitude' => $coordinates['lon'] ?? null,
            'last_seen_at' => now(),
        ]);

        if ($isFirstDevice || ! $this->alertEnabled($user)) {
            return;
        }

        $params = ['device' => $label, 'country' => $country ?? '—'];
        $user->notify(new GenericEventNotification(
            NotificationEvent::SecurityNewDevice,
            [
                'title' => (string) __('notification.message.new_device_title', $params),
                'title_key' => 'notification.message.new_device_title',
                'title_params' => $params,
                'message' => (string) __('notification.message.new_device_message', $params),
                'message_key' => 'notification.message.new_device_message',
                'message_params' => $params,
                'url' => route('account.2fa.show'),
            ],
            ['database', 'mail'],
        ));
    }

    private function alertEnabled(User $user): bool {
        return (bool) (($user->preferences[self::PREFERENCE_KEY] ?? true) !== false);
    }

    private function country(?string $ip): ?string {
        if ($ip === null || $ip === '') {
            return null;
        }
        try {
            $location = IpLocationHelper::lookup($ip);
        } catch (\Throwable) {
            return null;
        }

        $country = $location['country'] ?? null;

        return is_string($country) && $country !== '' ? $country : null;
    }
}
