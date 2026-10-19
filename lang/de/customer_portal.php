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
        'document_unavailable' => 'Das Dokument liegt im Buchhaltungsprogramm noch nicht vor. Bitte versuchen Sie es später erneut.',
    ],

    // Vorschau als Kunde (Phase 153, MVP-1229–1231).
    'preview' => [
        'action' => 'Portal ansehen',
        'action_as' => 'Als diese Person ansehen',
        'no_account' => 'Vorschau erst nach dem Anlegen eines Zugangs',
        'running' => 'Vorschau läuft als :name bis :until Uhr.',
        'stop' => 'Vorschau beenden',
        'banner' => 'Vorschau: Sie sehen das Portal als :name (:customer). Aktionen sind gesperrt.',
        'banner_until' => 'Endet um :time Uhr.',
        'title_prefix' => 'Vorschau',
        'blocked' => 'In der Vorschau sind Aktionen gesperrt.',
        'blocked_page' => 'Diese Seite ist in der Vorschau nicht verfügbar.',
        'nested_support' => 'Während einer Support-Sitzung ist keine Vorschau möglich.',
        'deactivated' => 'Für einen deaktivierten Zugang gibt es keine Vorschau.',
        'ended_manual' => 'Vorschau beendet.',
        'ended_replaced' => 'Die vorherige Vorschau wurde beendet.',
        'ended_expired' => 'Die Vorschau ist nach :minutes Minuten abgelaufen.',
        'ended_actor_changed' => 'Die Vorschau wurde beendet, weil sich die Anmeldung geändert hat.',
        'ended_permission_lost' => 'Die Vorschau wurde beendet, weil das Recht dafür fehlt.',
        'ended_organization_changed' => 'Die Vorschau wurde beendet, weil die Organisation gewechselt hat.',
        'ended_account_changed' => 'Die Vorschau wurde beendet, weil sich das Portalkonto geändert hat.',
        'ended_account_deactivated' => 'Die Vorschau wurde beendet, weil das Portalkonto nicht mehr verfügbar ist.',
    ],
    // Zugang mit Startpasswort (MVP-1232).
    'direct' => [
        'action' => 'Zugang anlegen',
        'title' => 'Portalzugang mit Startpasswort anlegen',
        'intro' => 'Sie erhalten ein zufälliges Startpasswort, das nur einmal angezeigt wird. Übergeben Sie es persönlich oder telefonisch, nie per E-Mail. Bei der ersten Anmeldung legt die Person ein eigenes Passwort fest.',
        'submit' => 'Zugang anlegen',
        'flash' => 'Zugang für :email angelegt.',
        'password_heading' => 'Startpasswort für :email',
        'password_hint' => 'Wird nur einmal angezeigt. Persönlich oder telefonisch übergeben, nie per E-Mail. Bei der ersten Anmeldung muss ein eigenes Passwort festgelegt werden.',
        'copy' => 'Kopieren',
        'copied' => 'Kopiert',
        'state_provisional' => 'Startpasswort ausstehend',
        'reissue_action' => 'Startpasswort erzeugen',
        'reissue_confirm_title' => 'Neues Startpasswort erzeugen',
        'reissue_confirm_message' => 'Das bisherige Passwort von :email gilt sofort nicht mehr, eine offene Einladung verfällt, alle Sitzungen werden beendet. Das neue Startpasswort wird einmal angezeigt; bei der nächsten Anmeldung muss ein eigenes Passwort festgelegt werden. Zwei-Faktor-Methoden bleiben bestehen.',
        'reissue_confirm_label' => 'Erzeugen',
        'reissue_flash' => 'Neues Startpasswort für :email erzeugt.',
        'reauth_hint' => 'Das Anlegen eines Zugangs mit Startpasswort verlangt eine frische Anmeldung. Bestätigen Sie zuerst Ihr Passwort und öffnen Sie den Dialog danach erneut.',
        'reauth_action' => 'Passwort bestätigen',
    ],
    // Passwort ändern im Portal (MVP-1233).
    'password_change' => [
        'title' => 'Passwort ändern',
        'forced_hint' => 'Sie haben ein Startpasswort erhalten. Bitte legen Sie jetzt Ihr eigenes Passwort fest, bevor Sie das Portal nutzen.',
        'intro' => 'Geben Sie Ihr bisheriges Passwort und ein neues Passwort ein. Anmeldungen dieses Zugangs auf anderen Geräten werden beendet.',
        'current' => 'Bisheriges Passwort',
        'new' => 'Neues Passwort',
        'confirm' => 'Neues Passwort wiederholen',
        'submit' => 'Passwort speichern',
        'flash' => 'Ihr Passwort wurde geändert.',
        'profile_heading' => 'Passwort',
        'profile_intro' => 'Ändern Sie Ihr Passwort, wenn Sie vermuten, dass es jemand kennt, oder wenn Sie ein Startpasswort erhalten haben.',
        'profile_link' => 'Passwort ändern',
    ],
];
