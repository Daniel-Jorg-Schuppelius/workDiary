<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationInboxStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Falls der Zuordnungs-Inbox: offen, bis er zugeordnet, angelegt, als Konflikt gelöst oder verworfen ist. */
enum IntegrationInboxStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case ResolvedLinked = 'resolved_linked';
    case ResolvedCreated = 'resolved_created';
    case ResolvedLocal = 'resolved_local';
    case ResolvedRemote = 'resolved_remote';
    case Dismissed = 'dismissed';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('enums.integration.integration_inbox_status.open'),
            self::ResolvedLinked => (string) __('enums.integration.integration_inbox_status.resolved_linked'),
            self::ResolvedCreated => (string) __('enums.integration.integration_inbox_status.resolved_created'),
            self::ResolvedLocal => (string) __('enums.integration.integration_inbox_status.resolved_local'),
            self::ResolvedRemote => (string) __('enums.integration.integration_inbox_status.resolved_remote'),
            self::Dismissed => (string) __('enums.integration.integration_inbox_status.dismissed'),
        };
    }

    /**
     * Ein erledigter Fall geht wieder auf, wenn die Quelle ihn erneut meldet
     * (Konflikte der Plugins schreiben per `updateOrCreate`).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::ResolvedLinked, self::ResolvedCreated, self::ResolvedLocal, self::ResolvedRemote, self::Dismissed],
            self::ResolvedLinked, self::ResolvedCreated, self::ResolvedLocal, self::ResolvedRemote, self::Dismissed => [self::Open],
        };
    }
}
