<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail\Contracts;

use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Services\Mail\ParsedMessage;

/**
 * Erweiterungspunkt des Mail-Eingangs (MVP-873): ein Fachmodul übernimmt
 * Nachrichten, die es erkennt (openTRANS-Bestellung, E-Rechnung,
 * Anrufbericht, Ticket-Antwort), bevor die Nachricht in die Integrations-
 * Inbox fällt. Registrierung über `Manifest::extensions()`.
 */
interface MailIntakeHandler {
    /** Reihenfolge der Handler, kleinere zuerst — spezifische Anhänge vor Ticket-Threading. */
    public function priority(): int;

    /**
     * Ergebnis des Eingangs oder null, wenn der Handler nicht zuständig ist.
     *
     * @return 'b2b_order'|'einvoice'|'callreport'|'ticket_message'|'skipped'|null
     */
    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string;
}
