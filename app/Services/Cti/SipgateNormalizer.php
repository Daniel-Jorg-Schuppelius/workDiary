<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SipgateNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

/**
 * Adapter für sipgate.io-Webhooks (Feature 056, MVP-118, Referenzprovider).
 * sipgate sendet mehrere Ereignisse je Anruf (newCall/answer/hangup);
 * protokolliert wird nur das terminale `hangup` (vollständiger Anruf).
 */
class SipgateNormalizer extends HangupEventNormalizer {
    protected function terminalEvent(): string {
        return 'hangup';
    }

    protected function callIdKey(): string {
        return 'callId';
    }
}
