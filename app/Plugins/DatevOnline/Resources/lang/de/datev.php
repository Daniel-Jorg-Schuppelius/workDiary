<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : datev.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// DATEV-Online-Plugin (MVP-122), Aufruf mit `datev-online::datev.…`.
return [
    'plugin' => [
        'description' => 'DATEV-Online: Anmeldung mit DATEV, Buchungsstapel per EXTF-Import statt Download und Belegbilder nächtlich an DATEV Unternehmen online.',
    ],
    'settings' => [
        'client_id' => 'Client-ID (DATEV-Entwicklerportal)',
        'client_id_help' => 'Aus der App-Registrierung bei DATEV; als Weiterleitungsadresse dort eintragen: :url',
        'client_secret' => 'Client-Secret',
        'sandbox' => 'Sandbox verwenden',
        'sandbox_help' => 'Testumgebung von DATEV. Produktiv erst nach Freigabe durch DATEV ausschalten.',
    ],
    'health' => [
        'not_configured' => 'Keine DATEV-App-Registrierung hinterlegt.',
        'not_connected' => 'Nicht mit DATEV angemeldet.',
        'no_client' => 'Kein Mandant gewählt.',
        'last_error' => 'Letzter Fehler: :error',
        'ok' => 'Verbunden mit :client.',
    ],
    'connection_status' => [
        'active' => 'verbunden',
        'disconnected' => 'nicht verbunden',
    ],
    'incoming' => [
        'label' => 'DATEV Unternehmen online',
    ],
    'transfer_kind' => [
        'extf' => 'Buchungsstapel',
        'outgoing_document' => 'Ausgangsrechnung',
        'incoming_document' => 'Eingangsrechnung',
    ],
    'transfer_status' => [
        'pending' => 'in Verarbeitung',
        'transferred' => 'übertragen',
        'succeeded' => 'importiert',
        'failed' => 'fehlgeschlagen',
    ],
    'error' => [
        'unknown_client' => 'Dieser Mandant ist für die Anmeldung nicht freigegeben.',
        'not_ready' => 'Erst mit DATEV anmelden und einen Mandanten wählen.',
        'batch_not_exported' => 'Nur abgeschlossene Buchungsstapel lassen sich übertragen.',
        'client_mismatch' => 'Der Stapel gehört zu einem anderen Berater oder Mandanten als dem verbundenen.',
        'file_missing' => 'Die Stapeldatei fehlt im Speicher.',
        'transfer_failed' => 'DATEV hat den Stapel nicht angenommen; Einzelheiten stehen in der Liste.',
    ],
    'flash' => [
        'not_configured' => 'DATEV-Online ist nicht eingerichtet: Client-ID und Client-Secret fehlen.',
        'state_invalid' => 'Ungültiger oder abgelaufener Anmeldestatus — bitte erneut anmelden.',
        'oauth_denied' => 'Die Anmeldung bei DATEV wurde abgebrochen.',
        'oauth_failed' => 'Token-Austausch mit DATEV fehlgeschlagen (:class).',
        'connected' => 'Mit DATEV angemeldet. Bitte den Mandanten wählen.',
        'disconnected' => 'Verbindung zu DATEV getrennt.',
        'client_selected' => 'Mandant :client gewählt.',
        'documents_saved' => 'Einstellungen für Belegbilder gespeichert.',
        'uploaded' => ':transferred Belege übertragen, :failed fehlgeschlagen.',
        'batch_transferred' => 'Stapel :no an DATEV übergeben; DATEV verarbeitet den Import.',
        'jobs_refreshed' => ':count Importe abgeschlossen.',
    ],
    'page' => [
        'subtitle' => 'Buchungsstapel und Belegbilder direkt an DATEV Unternehmen online übertragen.',
        'sandbox' => 'Sandbox',
        'connect' => 'Mit DATEV anmelden',
        'disconnect' => 'Trennen',
        'disconnect_confirm' => 'Verbindung zu DATEV wirklich trennen?',
        'not_configured' => 'In den Plugin-Einstellungen fehlen Client-ID und Client-Secret der DATEV-App-Registrierung.',
        'client' => [
            'heading' => 'Mandant',
            'choose' => 'Mandant (Berater-Mandant)',
            'save' => 'Übernehmen',
            'error' => 'Die Mandantenliste ist nicht abrufbar (:class).',
            'none' => 'Für diese Anmeldung sind keine Mandanten freigegeben.',
        ],
        'documents' => [
            'heading' => 'Belegbilder',
            'hint' => 'Ausgestellte Rechnungen gehen als „Rechnungsausgang“ an DATEV Unternehmen online, eingegangene Rechnungen als „Rechnungseingang“, sobald sie im Rechnungseingang zugeordnet sind — jeweils einmal, ab dem gewählten Datum.',
            'enabled' => 'Belegbilder nächtlich übertragen',
            'since' => 'Ab Belegdatum',
            'save' => 'Speichern',
            'upload' => 'Jetzt übertragen',
        ],
        'batches' => [
            'heading' => 'Buchungsstapel',
            'hint' => 'Abgeschlossene Stapel gehen als EXTF-Import an DATEV; Berater und Mandant im Stapel müssen zum verbundenen Mandanten passen.',
            'empty' => 'Keine abgeschlossenen Buchungsstapel.',
            'transfer' => 'An DATEV übertragen',
            'refresh' => 'Importstatus abfragen',
            'col' => [
                'batch' => 'Stapel',
                'period' => 'Zeitraum',
                'client' => 'Berater-Mandant',
                'status' => 'DATEV',
            ],
        ],
        'transfers' => [
            'heading' => 'Zuletzt übertragene Belege',
            'empty' => 'Noch keine Belege übertragen.',
            'col' => [
                'kind' => 'Art',
                'date' => 'Zeitpunkt',
                'status' => 'Stand',
            ],
        ],
    ],
];
