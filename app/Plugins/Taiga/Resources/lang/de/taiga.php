<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taiga.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Userstories ausgewählter Taiga-Projekte als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, offen oder geschlossen und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In Taiga öffnen',
    'health' => [
        'not_configured' => 'Taiga ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu Taiga noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der API: für Taiga Cloud https://api.taiga.io, sonst die Adresse der eigenen Instanz (ohne /api/v1).',
        'username' => 'Benutzername',
        'username_help' => 'Benutzername oder E-Mail-Adresse eines Kontos mit Lesezugriff auf die Projekte; am besten ein eigenes Konto für die Anbindung. Das Konto braucht in den Projekten das Recht, Userstories zu sehen.',
        'password' => 'Passwort',
        'password_help' => 'Taiga vergibt keine dauerhaften API-Token; die Anbindung meldet sich je Lauf neu an. Konten mit Anmeldung über GitHub oder GitLab brauchen ein eigenes Passwort.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
