<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacturationTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance\Targets;

use App\Enums\Finance\TransferTarget;
use App\Models\Finance\BillingTransfer;

/**
 * Ziel-Adapter für Übergabenachweise (Feature 045, Teil B): Übersetzt einen
 * bestätigten {@see BillingTransfer} in das jeweilige Fakturierungsziel
 * (Lexoffice-Rechnungsentwurf, Datei-Übergabepaket, später DATEV Desktop API).
 *
 * Verantwortlichkeiten:
 *  - transfer() führt NUR die Ziel-Seite aus (API-Call bzw. Datei) und liefert
 *    den Nachweis als {@see TargetResult} zurück.
 *  - Die Statusmaschine (markTransferred/markFailed) bleibt beim Aufrufer
 *    (Controller + BillingTransferService) — Fehler werden als Exception
 *    hochgereicht, NICHT verschluckt.
 */
interface FacturationTarget {
    public function supports(TransferTarget $target): bool;

    /**
     * @throws \RuntimeException bei HTTP-/Validierungs-/Schreibfehlern des Ziels.
     */
    public function transfer(BillingTransfer $transfer): TargetResult;
}
