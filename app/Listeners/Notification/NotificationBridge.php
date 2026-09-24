<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationBridge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\Contracts\NotifiesUsers;
use App\Services\Notification\NotificationDispatcher;

/** Brücke Domain-Event → Benachrichtigungsregeln (MVP-863); registriert auf das Interface, nicht auf einzelne Events. */
final class NotificationBridge {
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(NotifiesUsers $event): void {
        $this->dispatcher->notify($event->notificationEvent(), $event->notificationSubject(), $event->notificationAffected(), $event->notificationPayload());
    }
}
