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
    'intro' => 'Tickets einer zugeordneten Zammad-Gruppe kommen als Aufgaben in WorkDiary an — für Zeiterfassung, Nachweise und Abrechnung. Das Ticketsystem bleibt führend; ein erneuter Import erzeugt keine Dubletten.',

    'health' => [
        'ok' => 'Verbunden',
        'failing' => 'Nicht erreichbar',
        'inactive' => 'Inaktiv',
    ],

    'action' => [
        'sync' => 'Jetzt importieren',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
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
        'default_project' => 'Standard-Projekt',
        'no_project' => '— ohne Projekt (global) —',
        'active' => 'Aktiv',
        'resolved_state' => 'Status-Rückmeldung (Zielstatus)',
        'resolved_state_help' => 'Optional: Zielstatus im Ticket, wenn die Aufgabe erledigt wird (z. B. »closed«). Leer = aus.',
    ],

    'queue' => [
        'heading' => 'Queue → Projekt',
        'help' => 'Ordnet Zammad-Gruppen (Gruppen-ID) einem WorkDiary-Projekt zu. Ohne Treffer greift das Standard-Projekt, sonst wird die Aufgabe global angelegt.',
        'group_id' => 'Gruppen-ID',
    ],

    'flash' => [
        'saved' => 'Zammad-Anbindung gespeichert.',
        'sync_done' => 'Ticket-Import gestartet.',
        'disconnected' => 'Zammad-Anbindung getrennt. Aufgaben und Verknüpfungen bleiben erhalten.',
        'no_connection' => 'Keine aktive Zammad-Anbindung vorhanden.',
        'invalid_url' => 'Die Instanz-URL muss mit http:// oder https:// beginnen.',
        'token_required' => 'Für eine neue Anbindung ist ein API-Token erforderlich.',
    ],
    'resolution' => [
        'note' => 'In WorkDiary erledigt.',
    ],
];
