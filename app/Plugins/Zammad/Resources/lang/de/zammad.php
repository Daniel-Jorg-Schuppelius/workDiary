<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : zammad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Zammad',
    'intro' => 'Offene Tickets, die das API-Token sieht, kommen als Aufgaben in WorkDiary an — für Zeiterfassung, Nachweise und Abrechnung. Mit „Nur zugeordnete Gruppen“ sind es nur die Gruppen mit Projektzuordnung. Das Ticketsystem bleibt führend; ein erneuter Import erzeugt keine Dubletten.',

    'health' => [
        'ok' => 'Verbunden',
        'failing' => 'Nicht erreichbar',
        'inactive' => 'Inaktiv',
    ],

    'action' => [
        'sync' => 'Jetzt importieren',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
        'switch_target' => 'Ziel wechseln',
    ],

    'connection' => [
        'heading' => 'Anbindung',
    ],

    'field' => [
        'name' => 'Bezeichnung',
        'base_url' => 'Instanz-URL',
        'api_token' => 'API-Token',
        'token_keep' => '•••••••• (unverändert lassen)',
        'token_help' => 'Zammad: Profil → Token-Zugriff. Wird verschlüsselt gespeichert.',
        'webhook_secret' => 'Webhook-Secret (optional)',
        'webhook_help' => 'Shared-Secret für die Webhook-Signatur (X-Hub-Signature). Leer = Webhook aus, nur Polling.',
        'webhook_url' => 'Webhook-Adresse (in Zammad unter Webhook als Endpunkt eintragen, das Secret als HMAC-SHA1-Signatur-Token)',
        'default_project' => 'Standard-Projekt',
        'no_project' => '— ohne Projekt (global) —',
        'active' => 'Aktiv',
        'resolved_state' => 'Status-Rückmeldung (Zielstatus)',
        'resolved_state_help' => 'Optional: Zielstatus im Ticket, wenn die Aufgabe erledigt wird (z. B. »closed«). Leer = aus.',
        'time_unit' => 'Zeitbuchung ins Ticket',
        'time_unit_off' => 'Aus',
        'time_unit_minute' => 'Minuten',
        'time_unit_hour' => 'Stunden',
        'time_unit_help' => 'Optional: bucht Zeiten, die zu verknüpften Aufgaben erfasst werden, als Zeiterfassung ins Ticket. Die Einheit muss zur Einheit der Zeiterfassung in Zammad passen. Aus = keine Zeitbuchung.',
        'allow_private_network' => 'Private/interne Adressen erlauben',
        'allow_private_network_help' => 'Nur einschalten, wenn Zammad im eigenen Netz steht (z. B. 192.168.x.x). Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],

    'queue' => [
        'heading' => 'Queue → Projekt',
        'help' => 'Ordnet Zammad-Gruppen (Gruppen-ID) einem WorkDiary-Projekt zu. Ohne Treffer greift das Standard-Projekt, sonst wird die Aufgabe global angelegt.',
        'group_id' => 'Gruppen-ID',
        'limited' => 'Nur zugeordnete Gruppen',
        'limited_help' => 'An: Nur Tickets der Gruppen, die hier einem Projekt zugeordnet sind, kommen als Aufgaben an. Aus: alle Tickets, die das API-Token sieht.',
    ],

    'flash' => [
        'saved' => 'Zammad-Anbindung gespeichert.',
        'sync_done' => 'Ticket-Import gestartet.',
        'disconnected' => 'Zammad-Anbindung getrennt. Aufgaben und Verknüpfungen bleiben erhalten.',
        'no_connection' => 'Keine aktive Zammad-Anbindung vorhanden.',
        'invalid_url' => 'Die Instanz-URL muss mit http:// oder https:// beginnen.',
        'token_required' => 'Für eine neue Anbindung ist ein API-Token erforderlich.',
        'private_url_blocked' => 'Die Instanz-URL zeigt auf eine private/interne Adresse. Für Zammad im eigenen Netz die Freigabe privater Adressen aktivieren.',
        'helpdesk_required' => 'Service-Tickets setzen das Modul Helpdesk voraus.',
        'queue_required' => 'Bitte wählen Sie eine Queue.',
        'target_switched' => 'Ticketziel gewechselt.',
    ],

    'target' => [
        'heading' => 'Ticketziel',
        'help' => 'Legt fest, ob neue Tickets als Aufgaben oder als Service-Tickets einer Queue ankommen. Bereits importierte Tickets bleiben, wo sie sind. Offene Zuordnungskonflikte in der Zuordnungs-Inbox verhindern den Wechsel.',
        'current' => 'Aktuell',
        'task' => 'Aufgaben',
        'service_ticket' => 'Service-Tickets',
        'service_ticket_in' => 'Service-Tickets in „:queue“',
        'field' => 'Neue Tickets als',
        'queue' => 'Queue',
        'queue_help' => 'Nur für Service-Tickets. Zammad führt danach die Tickets dieser Queue.',
        'no_queue' => '— Queue wählen —',
        'helpdesk_hint' => 'Service-Tickets setzen das Modul Helpdesk voraus.',
        'no_queues_hint' => 'Es gibt noch keine Queue. Legen Sie sie unter Service Desk → Queues an.',
        'confirm_title' => 'Ticketziel wechseln',
        'confirm' => 'Neue Tickets kommen danach auf dem gewählten Weg an; bereits importierte bleiben, wo sie sind. Der Wechsel wird protokolliert.',
    ],

    'guard' => [
        'subject' => 'Die Instanz-URL',
        'private_hint' => 'Für Zammad im eigenen Netz muss an der Anbindung die Freigabe privater Adressen aktiviert sein.',
    ],

    'resolution' => [
        'note' => 'In WorkDiary erledigt.',
    ],
];
