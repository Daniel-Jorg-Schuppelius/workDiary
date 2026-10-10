<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Kundenportal: Passwort vergessen, Zugang zurücksetzen, Rechnungsdokument (MVP-1096/1097).
return [
    'password' => [
        'mail_subject' => 'Passwort für das Kundenportal von :org zurücksetzen',
        'mail_heading' => 'Passwort zurücksetzen',
        'mail_intro' => 'Für Ihren Zugang zum Kundenportal von :org wurde das Zurücksetzen des Passworts angefordert. Über den folgenden Link legen Sie ein neues Passwort fest.',
        'mail_validity' => 'Der Link ist einmalig verwendbar und :minutes Minuten gültig.',
        'mail_ignore' => 'Wenn Sie das nicht angefordert haben, ignorieren Sie diese E-Mail — Ihr bisheriges Passwort bleibt gültig.',
        'sessions_hint' => 'Mit dem neuen Passwort werden alle bestehenden Anmeldungen dieses Zugangs beendet. Ein eingerichteter zweiter Faktor bleibt bestehen.',
    ],
    'reset' => [
        'action' => 'Zugang zurücksetzen',
        'confirm_title' => 'Portalzugang zurücksetzen',
        'confirm_message' => 'Das bisherige Passwort gilt sofort nicht mehr, alle Sitzungen werden beendet, und :email erhält eine neue Einladung. Eingerichtete Zwei-Faktor-Methoden bleiben bestehen.',
        'confirm_label' => 'Zurücksetzen',
        'flash' => 'Zugang zurückgesetzt — neue Einladung an :email versendet.',
        'mail_heading' => 'Ihr Zugang zum Kundenportal wurde zurückgesetzt',
        'mail_intro' => ':org hat Ihren Zugang zum Kundenportal zurückgesetzt. Ihr bisheriges Passwort gilt nicht mehr. Über den folgenden Link legen Sie ein neues Passwort fest und melden sich anschließend an.',
    ],
    'second_factor' => [
        'action' => 'Zweiten Faktor zurücksetzen',
        'confirm_title' => 'Zweiten Faktor zurücksetzen',
        'confirm_message' => 'Alle Zwei-Faktor-Methoden von :email (App, Passkeys, Wiederherstellungscodes) werden entfernt und alle Sitzungen beendet. Nutzen Sie das nur, wenn der Kunde keinen Faktor mehr besitzt und Sie seine Identität geprüft haben.',
        'confirm_label' => 'Zurücksetzen',
        'flash' => 'Zweiter Faktor von :email zurückgesetzt — der Kunde wurde informiert.',
        'mail_subject' => 'Zwei-Faktor-Anmeldung im Kundenportal von :org zurückgesetzt',
        'mail_heading' => 'Ihr zweiter Faktor wurde zurückgesetzt',
        'mail_intro' => ':org hat die Zwei-Faktor-Methoden Ihres Zugangs zum Kundenportal entfernt. Sie melden sich mit Ihrem Passwort an; verlangt :org eine Zwei-Faktor-Anmeldung, richten Sie sie dabei neu ein.',
        'mail_unexpected' => 'Wenn Sie das nicht veranlasst haben, wenden Sie sich bitte umgehend an Ihre Ansprechperson bei :org.',
    ],
    'invoices' => [
        'pdf_label' => 'Rechnung :number als PDF herunterladen',
    ],
];
