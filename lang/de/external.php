<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : external.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'party' => [
        'subcontractor' => 'Subunternehmer',
        'inspector' => 'Prüfer',
        'expert' => 'Sachverständiger',
        'other' => 'Sonstige',
    ],
    'ability' => [
        'view' => 'Ansehen',
        'comment' => 'Kommentieren',
        'upload' => 'Datei hochladen',
        'confirm' => 'Bestätigen',
    ],
    'status' => [
        'invited' => 'Eingeladen',
        'accessed' => 'Zugegriffen',
        'expired' => 'Abgelaufen',
        'revoked' => 'Widerrufen',
    ],
    'subject' => [
        'order' => 'Auftrag',
        'generic' => 'Vorgang',
    ],
    'panel' => [
        'title' => 'Externe Beteiligte',
        'invite' => 'Einladen',
        'empty' => 'Noch keine externen Beteiligten eingeladen.',
        'link_once' => 'Diesen Link einmalig kopieren und an den Externen senden — er wird nicht erneut angezeigt.',
    ],
    'col' => [
        'name' => 'Name',
        'party' => 'Art',
        'abilities' => 'Rechte',
        'status' => 'Status',
        'expires' => 'Gültig bis',
    ],
    'group' => [
        'contact' => 'Kontakt',
        'abilities' => 'Erlaubte Aktionen',
        'validity' => 'Gültigkeit',
    ],
    'field' => [
        'name' => 'Name',
        'email' => 'E-Mail (optional)',
        'role' => 'Rolle',
        'party' => 'Art',
        'ttl_days' => 'Gültigkeit (Tage)',
    ],
    'hint' => [
        'role' => 'z. B. Elektriker, TÜV-Prüfer',
        'abilities' => 'Ansehen ist immer erlaubt. Zusätzliche Aktionen werden serverseitig streng durchgesetzt.',
        'ttl_days' => '1 bis 180 Tage. Danach läuft der Zugang automatisch ab.',
    ],
    'invite' => [
        'title' => 'Externen einladen',
        'eyebrow' => 'Externe Beteiligte',
        'submit' => 'Einladen & Link erzeugen',
        'once_hint' => 'Der Zugangslink wird nach dem Anlegen genau EINMAL angezeigt — nur der Hash wird gespeichert.',
    ],
    'revoke' => [
        'action' => 'Widerrufen',
        'title' => 'Zugang widerrufen',
        'message' => 'Der externe Zugang wird sofort gesperrt. Fortfahren?',
        'confirm' => 'Widerrufen',
    ],
    'flash' => [
        'invited' => 'Externer Beteiligter „:name" eingeladen.',
        'invited_emailed' => 'Externer Beteiligter „:name" eingeladen und Zugangslink per E-Mail versendet.',
        'revoked' => 'Externer Zugang widerrufen.',
    ],
    'mail' => [
        'subject' => 'Ihr Zugang zu freigegebenen Unterlagen',
        'heading' => 'Externer Zugang',
        'intro' => 'Hallo :name, Sie wurden zu freigegebenen Unterlagen eingeladen. Über den folgenden Link erhalten Sie ohne Anmeldung Zugriff:',
        'button' => 'Zugang öffnen',
        'expires' => 'Der Zugang ist gültig bis :date.',
        'note' => 'Bitte geben Sie diesen Link nicht weiter — er ist persönlich und zeitlich befristet.',
    ],
    'contact' => [
        'title' => 'Externe Kontaktprofile',
        'intro' => 'Wiederkehrende externe Beteiligte (Subunternehmer, Prüfer …) als wiederverwendbare Stammdaten.',
        'new' => 'Neues Profil',
        'edit' => 'Profil bearbeiten',
        'eyebrow' => 'Externe Kontaktprofile',
        'submit' => 'Speichern',
        'notes' => 'Notizen',
        'delete' => 'Löschen',
        'confirm_delete' => 'Dieses Kontaktprofil löschen? Bestehende Einladungen bleiben erhalten.',
        'empty' => 'Noch keine Kontaktprofile angelegt.',
        'pick' => 'Bestehendes Profil wählen (optional)',
        'pick_none' => '— Neu erfassen —',
        'save_as' => 'Diese Angaben als wiederverwendbares Profil speichern',
        'flash' => [
            'created' => 'Kontaktprofil angelegt.',
            'updated' => 'Kontaktprofil aktualisiert.',
            'deleted' => 'Kontaktprofil gelöscht.',
        ],
    ],
    'public' => [
        'title' => 'Externer Zugang',
        'hello' => 'Hallo :name',
        'expires_note' => 'Dieser Zugang ist gültig bis :date.',
        'view_only' => 'Dieser Zugang ist auf das Ansehen beschränkt.',
        'comment_heading' => 'Kommentar hinterlassen',
        'comment_placeholder' => 'Ihre Anmerkung …',
        'comment_submit' => 'Kommentar senden',
        'comment_saved' => 'Kommentar gespeichert.',
        'upload_heading' => 'Datei oder Foto hochladen',
        'upload_hint' => 'Erlaubt: JPG, PNG, GIF, WEBP, PDF (max. 25 MB).',
        'upload_submit' => 'Hochladen',
        'upload_saved' => 'Datei hochgeladen.',
        'upload_rejected' => 'Dateityp nicht erlaubt.',
        'confirm_heading' => 'Bestätigen / Abnehmen',
        'confirm_note_placeholder' => 'Optionale Anmerkung zur Bestätigung …',
        'confirm_accept' => 'Ich bestätige die Richtigkeit der Angaben.',
        'confirm_submit' => 'Bestätigen',
        'confirmed' => 'Bestätigung gespeichert.',
    ],
];
