<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPushTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten\Contracts;

use App\Enums\Numbering\ContactNumberOutcome;
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;

/**
 * Korrigierte Stammdaten an das führende Buchhaltungssystem übertragen
 * (Welle 4.5): definiert von der Plattform (Stammdaten), gebunden vom
 * Finanzmodul (`ContactPushService`). Null-Bindung: kein Push.
 */
interface ContactPushTarget {
    /** Führt workDiary die Stammdaten? Nur dann darf übertragen werden. */
    public function pushAllowed(): bool;

    /** @return string externe ID */
    public function push(Customer $customer, string $pluginId): string;

    /** @return string externe ID */
    public function pushSupplier(Supplier $supplier, string $pluginId): string;

    /**
     * Überträgt einen Kontakt mit Entwurfsnummer an das Programm, das seine
     * Nummer vergibt, und übernimmt sie (MVP-1131). Null: nichts zu tun —
     * keine Programmvergabe, keine Entwurfsnummer oder die Buchhaltung führt
     * die Stammdaten.
     */
    public function issueNumber(Customer|Supplier $party): ?ContactNumberOutcome;
}
