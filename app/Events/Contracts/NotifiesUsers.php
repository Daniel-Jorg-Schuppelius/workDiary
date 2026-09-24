<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotifiesUsers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Contracts;

use App\Enums\Notification\NotificationEvent;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Domain-Event, das eine Benachrichtigung auslösen soll (MVP-863): Die
 * {@see \App\Listeners\Notification\NotificationBridge} reicht es an den
 * {@see \App\Services\Notification\NotificationDispatcher} durch — das
 * emittierende Modul kennt weder Regeln noch Kanäle.
 */
interface NotifiesUsers {
    public function notificationEvent(): NotificationEvent;

    public function notificationSubject(): Model;

    public function notificationAffected(): ?User;

    /** @return array{title: string, message?: string|null, url?: string|null, icon?: string|null, due_at?: \DateTimeInterface|string|null} */
    public function notificationPayload(): array;
}
