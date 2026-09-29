<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Invoicing\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Buchhaltungsprogramm, das die Rechnungen des Pauschal-Modus führt
 * (Feature 098, MVP-1027): es stellt Monatspauschale und Spitzabrechnung aus
 * und verfolgt die Zahlung. Plugins registrieren sich beim
 * {@see \App\Services\Billing\RetainerChannelResolver}; ohne Plugin antwortet
 * {@see \App\Services\Billing\NullRetainerPublisher}.
 */
interface RetainerPublisher {
    /** Anzeigename für Meldungen; null = kein Programm angebunden. */
    public function label(): ?string;

    public function isConfigured(): bool;

    /**
     * Monatspauschale erzeugen und übergeben; idempotent je Monat.
     *
     * @throws ValidationException fachlich abgewiesen (kein Betrag, Beleg schon verknüpft, nicht eingerichtet)
     */
    public function pushMonthlyRetainer(CustomerBillingAgreement $agreement, int $year, int $month): ?Invoice;

    /**
     * Spitzabrechnung über den offenen Leistungssaldo.
     *
     * @throws ValidationException
     */
    public function pushTrueUp(CustomerBillingAgreement $agreement, ?CarbonInterface $cutoff = null): Invoice;
}
