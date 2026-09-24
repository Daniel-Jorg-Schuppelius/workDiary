<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventOccurrenceCreated.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Calendar;

use App\Models\Calendar\Event;
use Illuminate\Foundation\Events\Dispatchable;

/** Serientermin-Instanz angelegt — Module erben ihre Zusatzdaten vom Master (synchron, wie der frühere Observer). */
final class EventOccurrenceCreated {
    use Dispatchable;

    public function __construct(public readonly Event $event) {}
}
