<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemotePendingSessionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Enums;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Fernwartungs-Sitzung in der Inbox: offen, als Zeit übernommen oder verworfen. */
enum RemotePendingSessionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Imported = 'imported';
    case Dismissed = 'dismissed';

    /** Verbindungsversuch ohne Dauer: dokumentiert, nie in der Inbox angeboten. */
    case Attempt = 'attempt';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('remote-support::enums.remote_pending_session_status.open'),
            self::Imported => (string) __('remote-support::enums.remote_pending_session_status.imported'),
            self::Dismissed => (string) __('remote-support::enums.remote_pending_session_status.dismissed'),
            self::Attempt => (string) __('remote-support::enums.remote_pending_session_status.attempt'),
        };
    }

    /**
     * Ein Versuch geht in der Buchung der folgenden Sitzung auf.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Imported, self::Dismissed],
            self::Attempt => [self::Imported],
            self::Imported, self::Dismissed => [],
        };
    }
}
