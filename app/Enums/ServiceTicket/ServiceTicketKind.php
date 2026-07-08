<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

/** Ticketart (Feature 065, MVP-151): steuert Prozess und Katalogbezug. */
enum ServiceTicketKind: string {
    case Incident = 'incident';
    case ServiceRequest = 'service_request';
    case Question = 'question';

    public function label(): string {
        return match ($this) {
            self::Incident => (string) __('Störung'),
            self::ServiceRequest => (string) __('Service-Anfrage'),
            self::Question => (string) __('Frage'),
        };
    }
}
