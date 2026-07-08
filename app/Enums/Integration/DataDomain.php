<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

/**
 * Datenbereiche mit konfigurierbarer Datenführerschaft (Restpunkt 69):
 * je Organisation und Bereich führt genau EIN System (native oder eine
 * Plugin-ID) — der {@see \App\Services\Integration\DataOwnershipResolver}
 * gate't Plugin-Schreiboperationen dagegen.
 */
enum DataDomain: string {
    case Tasks = 'tasks';
    case Tickets = 'tickets';
    case Inventory = 'inventory';
    case Calendar = 'calendar';
    case Documents = 'documents';
    case Customers = 'customers';

    public function label(): string {
        return match ($this) {
            self::Tasks => (string) __('Aufgaben'),
            self::Tickets => (string) __('Tickets'),
            self::Inventory => (string) __('Lagerbestand'),
            self::Calendar => (string) __('Kalender'),
            self::Documents => (string) __('Dokumente'),
            self::Customers => (string) __('Kunden'),
        };
    }
}
