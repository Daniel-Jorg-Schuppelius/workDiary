<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : redmine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Issues ausgewählter Redmine-Projekte als Aufgaben in WorkDiary-Projekte und auf Wunsch die Zeiteinträge als Zeitbuchungen, nur lesend: Titel, Beschreibung, Status über „geschlossen“ und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In Redmine öffnen',
    'health' => [
        'not_configured' => 'Redmine ist nicht eingerichtet (Basis-URL oder API-Schlüssel fehlt).',
        'not_connected' => 'Verbindung zu Redmine noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Basis-URL',
        'base_url_help' => 'Adresse der Redmine-Instanz, z. B. https://redmine.example.com.',
        'api_key' => 'API-Schlüssel',
        'api_key_help' => 'In Redmine unter Mein Konto → API-Zugriffsschlüssel; die REST-API muss in der Administration aktiviert sein. Das Konto braucht in den Projekten eine Rolle, die Tickets und gebuchte Aufwände sehen darf, mit Sichtbarkeit „alle“.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Basis-URL mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
        'default_billable' => 'Zeiten abrechenbar übernehmen',
        'default_billable_help' => 'Wenn aus, sind übernommene Zeiten nie abrechenbar; wenn an, gilt die Einstellung des Zielprojekts.',
        'time_window_days' => 'Zeitfenster (Tage)',
        'time_window_days_help' => 'Wie viele Tage zurück jeder Lauf die Zeiteinträge liest. Älteres bleibt unberührt.',
    ],
    'time' => [
        'fallback' => 'Redmine-Zeiteintrag',
    ],
    'flash' => [
        'times_enabled' => 'Zeitimport für diese Zuordnung eingeschaltet.',
        'times_disabled' => 'Zeitimport für diese Zuordnung ausgeschaltet. Bereits übernommene Zeiten bleiben.',
        'user_invalid' => 'Bitte einen Benutzer Ihrer Organisation wählen.',
        'user_mapped' => 'Benutzer zugeordnet. Der nächste Abgleich bucht die wartenden Zeiten.',
        'user_unmapped' => 'Zuordnung entfernt. Bereits gebuchte Zeiten bleiben.',
    ],
    'times' => [
        'title' => 'Zeiteinträge',
        'hint' => 'Je Zuordnung lassen sich die Redmine-Zeiteinträge der letzten :days Tage übernehmen. Redmine kennt nur Tag und Stunden, gebucht wird deshalb als Dauer ohne Uhrzeit im Zielprojekt; zum verknüpften Issue gehört die Aufgabe.',
        'col' => [
            'project' => 'Redmine-Projekt',
            'last_run' => 'Letzter Zeitimport',
            'import' => 'Zeiten übernehmen',
        ],
        'counters' => ':created neu · :updated geändert · :unresolved_users warten auf Benutzer · :removed entfernt',
        'enabled' => 'An',
        'disabled' => 'Aus',
        'empty' => 'Noch keine Projektzuordnung',
    ],
    'users' => [
        'title' => 'Benutzerzuordnung',
        'hint' => 'Zeiteinträge werden nur für zugeordnete Redmine-Benutzer gebucht, nie still für jemand anderen. Wartende Einträge bucht der nächste Abgleich nach der Zuordnung.',
        'waiting' => ':name (:count wartende Einträge)',
        'map' => 'Zuordnen',
        'none_pending' => 'Keine Zeiteinträge warten auf eine Benutzerzuordnung',
        'mapped' => 'Zugeordnet',
        'mapping' => 'Redmine-Benutzer #:redmine → :user',
        'unmap' => 'Zuordnung entfernen',
        'confirm_unmap' => 'Zuordnung entfernen? Bereits gebuchte Zeiten bleiben, neue Einträge warten wieder.',
    ],
];
