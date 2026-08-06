<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentIntakeSubscriptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\CloudIntake\CloudDocumentConnection;

/**
 * Optionale Erweiterung zu {@see DocumentIntakeSource} (MS365-Plan §8):
 * Provider mit Change-Notification-Unterstützung stellen hierüber ihre
 * Webhook-Subscription je Verbindung sicher bzw. melden sie ab. Webhooks
 * bleiben reine Aufwecksignale — der Delta-Lauf über den Checkpoint ist
 * weiterhin die wiederanlaufbare Wahrheit; Provider ohne Subscriptions
 * implementieren dieses Interface schlicht nicht.
 */
interface DocumentIntakeSubscriptions {
    /** Subscription anlegen/erneuern; wirft bei API-Fehlern (Aufrufer entscheidet über best effort). */
    public function intakeSubscribe(CloudDocumentConnection $connection): void;

    /** Subscription abmelden (idempotent, best effort). */
    public function intakeUnsubscribe(CloudDocumentConnection $connection): void;
}
