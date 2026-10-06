<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlacetelNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

/**
 * Adapter für Placetel-Notify-Webhooks (Feature 056, MVP-343). Placetel
 * sendet je Anruf mehrere Ereignisse (`IncomingCall`/`CallAccepted`/
 * `OutgoingCall`/`HungUp`); protokolliert wird nur das terminale `HungUp`
 * (vollständiger Anruf). Feldmapping des Notify-Formats:
 * `call_id`, `direction` (`in`|`out`), `from`, `to`, `duration` (Sekunden).
 */
class PlacetelNormalizer extends HangupEventNormalizer {
    protected function terminalEvent(): string {
        return 'hungup';
    }

    protected function callIdKey(): string {
        return 'call_id';
    }
}
