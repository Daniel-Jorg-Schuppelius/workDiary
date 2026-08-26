<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : notifications.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'push' => [
        /** Max characters for the push notification body preview. */
        'body_truncate' => (int) env('NOTIFICATIONS_PUSH_BODY_TRUNCATE', 120),
    ],

    /*
     * SMS-Kanal (Feature 147, MVP-730). Globale Defaults; je Organisation
     * überschreibbar über organizations.settings['notifications']['sms'].
     */
    'sms' => [
        /** Zeichen je Nachricht — nie über einem GSM-7-Segment (160). */
        'body_truncate' => (int) env('NOTIFICATIONS_SMS_BODY_TRUNCATE', 160),
        /**
         * Monatsdeckel in Segmenten; null = unbegrenzt. Bewusst konservativ
         * vorbelegt: SMS ist der einzige Kanal, der je Nachricht Geld kostet,
         * und eine Fehlkonfiguration soll eine Rechnung nicht ins Unendliche
         * treiben. 0 = Kanal faktisch aus.
         */
        'monthly_limit' => env('NOTIFICATIONS_SMS_MONTHLY_LIMIT', 250),
        /** Ab wie viel Prozent des Deckels gewarnt wird (0 = nie). */
        'warn_percent' => (int) env('NOTIFICATIONS_SMS_WARN_PERCENT', 80),
    ],
];
