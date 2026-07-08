<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Contracts;

/**
 * Normalisierter Zugriff auf Zammad-Tickets (Feature 060, MVP-129). Kapselt den
 * offiziellen `zammad/zammad-api-client-php` hinter einer schmalen, testbaren
 * Schnittstelle — die Importlogik hängt nur hieran (im Test gemockt).
 */
interface ZammadGateway {
    /**
     * Tickets (optional auf eine Gruppe/Queue gefiltert), normalisiert. `customer`
     * (E-Mail/Login) und `organization` (Name) stammen aus der expandierten
     * Ticket-Antwort und dienen dem Kundenvorschlag (Rang 21); leer, wenn nicht
     * gesetzt.
     *
     * @return list<array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null, customer: string, organization: string}>
     */
    public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array;

    /**
     * Leichter, authentifizierter Ping (Health-Check): true, wenn die
     * konfigurierte Zammad-Instanz mit dem Token erreichbar antwortet.
     */
    public function ping(): bool;

    /**
     * Schlanke Status-Rückmeldung (Feature 060, 2. Stufe): setzt den
     * Ticketstatus (`$state`, z. B. `closed`) und/oder hängt eine interne Notiz
     * (`$note`) an. Beide optional; true bei Erfolg. Das Ticketsystem bleibt
     * führend — es findet keine Vollsynchronisation statt.
     */
    public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool;

    /**
     * Bucht eine Zeit auf ein Ticket zurück (Feature 060, Rang 23): erzeugt einen
     * Time-Accounting-Eintrag (`POST /tickets/{id}/time_accountings`). `$timeUnit`
     * ist der rohe Wert gemäß der Zammad-„Time Accounting Unit"-Einstellung
     * (Minuten oder Stunden). true bei Erfolg.
     */
    public function accountTime(int $ticketId, float $timeUnit): bool;

    /** Artikel (Notiz/Antwort) am Ticket anlegen — internal steuert die Kundensichtbarkeit. */
    public function addArticle(int $ticketId, string $body, bool $internal = true): bool;
}
