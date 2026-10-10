<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTransferTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\Organization;
use App\Services\Invoicing\Dto\IncomingInvoiceTransferResult;

/**
 * Erweiterungspunkt (Feature 163, MVP-1111): ein Buchhaltungssystem nimmt
 * Rechnungseingänge entgegen. Plugins tragen sich über
 * `ModuleRegistry::contribute(IncomingInvoiceTransferTarget::class, …)` ein;
 * den Ablauf (Tor, Journal, Wiederholung) führt der Kern.
 */
interface IncomingInvoiceTransferTarget {
    /** Plugin-ID: Schlüssel im Übergabejournal und Absender in der Plugin-Fehler-Inbox. */
    public function key(): string;

    public function label(): string;

    /** In dieser Organisation eingeschaltet und betriebsbereit. */
    public function isEnabled(Organization $organization): bool;

    /** Gilt dieses Ziel für den Eingang (z. B. erst ab einem Stichtag)? */
    public function appliesTo(IncomingEInvoice $incoming): bool;

    /**
     * Übergibt den Eingang. `$journal` trägt eine schon vergebene externe ID
     * aus einem früheren, abgebrochenen Versuch; das Ziel setzt sie, sobald
     * der Beleg im Zielsystem existiert. Fehler werden geworfen.
     */
    public function transfer(IncomingEInvoice $incoming, IncomingEInvoiceTransfer $journal): IncomingInvoiceTransferResult;
}
