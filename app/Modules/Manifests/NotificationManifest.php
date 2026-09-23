<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Benachrichtigungen, Erinnerungen, Push, SMS“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class NotificationManifest extends Manifest {
    public function code(): string {
        return 'notification';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Benachrichtigungen, Erinnerungen, Push, SMS';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Notification',
            'Reminders',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'notification_dispatch_log',
            'notification_rules',
            'notifications',
            'push_subscriptions',
            'webhook_deliveries',
            'webhook_endpoints',
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'sevenio',
        ];
    }
}
