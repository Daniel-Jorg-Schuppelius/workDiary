<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyWebhookSubscriptionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Enums;

/** Stand einer Calendly-Webhook-Anmeldung (Feature 095): abgemeldete Zeilen bleiben als `disabled` stehen. */
enum CalendlyWebhookSubscriptionStatus: string {
    case Active = 'active';
    case Disabled = 'disabled';
}
