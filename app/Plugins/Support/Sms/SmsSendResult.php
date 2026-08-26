<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsSendResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Sms;

use App\Enums\Notification\SmsDeliveryStatus;

/**
 * Ergebnis eines SMS-Versands (Feature 147, MVP-730) — anbieterneutral.
 *
 * Trägt bewusst KEINEN Nachrichtentext: das Ergebnis landet im
 * `notification_dispatch_log` und im Audit, und dort hat der Inhalt einer
 * Alarmierung nichts zu suchen. `errorCode` ist der rohe Anbieter-Code
 * (z. B. seven.io „900", HTTP-Status) — er soll diagnostisch sein, nicht
 * schön; die Rufnummer steht nie darin.
 */
final readonly class SmsSendResult {
    public function __construct(
        public SmsDeliveryStatus $status,
        public ?string $providerMessageId = null,
        public int $segments = 1,
        public ?string $errorCode = null,
    ) {}

    public static function sent(?string $messageId = null, int $segments = 1): self {
        return new self(SmsDeliveryStatus::Sent, $messageId, max(1, $segments));
    }

    /** Fehlversuch: keine Zustellung, keine gezählten Segmente. */
    public static function failed(string $errorCode): self {
        return new self(SmsDeliveryStatus::Failed, null, 0, mb_substr($errorCode, 0, 64));
    }
}
