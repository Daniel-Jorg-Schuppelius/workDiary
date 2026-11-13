<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadConversionCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Contracts;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\Lead;

/**
 * Erweiterungspunkt der Lead-Umwandlung (Feature 200, MVP-1641): eine Prüfung
 * kann die Umwandlung sperren und nach ihr Daten an den Kunden übergeben.
 * Gelesen über `ModuleExtensions::activeFor()` im {@see \App\Services\Sales\LeadService}.
 * Ohne Beitrag, der sperrt oder übergibt, bleibt die Umwandlung wie vor
 * Feature 200 (Regressionstest `LeadIntakeConversionTest`).
 */
interface LeadConversionCheck {
    /** Grund, warum der Lead noch nicht umgewandelt werden darf; null = frei. */
    public function blockingReason(Lead $lead): ?string;

    /**
     * Nach dem Anlegen bzw. Verbinden des Kunden, in derselben Transaktion.
     *
     * @return list<string> Hinweise für die Person, die umgewandelt hat (etwa unvollständige Fristmerkmale)
     */
    public function converted(Lead $lead, Customer $customer, bool $newCustomer, User $actor): array;
}
