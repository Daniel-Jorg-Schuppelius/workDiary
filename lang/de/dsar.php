<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dsar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'portal' => [
        'title' => 'Datenschutzanfrage',
        'subtitle' => 'Ihre Rechte als betroffene Person',
        'footer' => 'Diese Seite dient ausschließlich der Ausübung Ihrer Betroffenenrechte. Bitte senden Sie hier keine Zahlungs- oder Zugangsdaten.',
    ],

    'landing' => [
        'title' => 'Datenschutzanfrage stellen',
        'intro' => 'Über dieses Verfahren können betroffene Personen ihre Rechte nach der Datenschutz-Grundverordnung geltend machen.',
        'no_link' => 'Für eine Anfrage benötigen Sie den Link der verantwortlichen Stelle. Wenden Sie sich an die Organisation, deren Daten Sie betreffen.',
        'rights' => 'Mögliche Anfragearten',
    ],

    'legal_note' => 'Die Angaben sind eine Information, keine Rechtsberatung. Maßgeblich ist der Gesetzestext.',
    'privacy_notice' => 'Ihre Angaben werden ausschließlich zur Bearbeitung dieser Anfrage verwendet, verschlüsselt gespeichert und nach Ablauf der Aufbewahrungsfrist gelöscht. Rechtsgrundlage ist Art. 6 Abs. 1 lit. c DSGVO in Verbindung mit den Art. 15 bis 21 DSGVO.',
    'identity_hint' => 'Vor einer Auskunft prüft die verantwortliche Stelle Ihre Identität (Art. 12 Abs. 6 DSGVO). Dazu kann sie sich gesondert bei Ihnen melden.',

    'form' => [
        'title' => 'Anfrage stellen',
        'what' => 'Worum geht es hier?',
        'what_text' => 'Sie können Auskunft über Ihre gespeicherten Daten verlangen, deren Berichtigung oder Löschung beantragen, die Verarbeitung einschränken lassen, Ihre Daten übertragen lassen oder der Verarbeitung widersprechen.',
        'submit' => 'Anfrage absenden',
    ],

    'field' => [
        'type' => 'Art der Anfrage',
        'full_name' => 'Vor- und Nachname',
        'email' => 'E-Mail-Adresse für die Rückmeldung',
        'reference' => 'Aktenzeichen, Kunden- oder Personalnummer (optional)',
        'message' => 'Ihr Anliegen',
        'attachments' => 'Anhänge (optional)',
        'attachments_hint' => 'Höchstens :max Dateien, je bis zu :size MB.',
        'honeypot' => 'Bitte nicht ausfüllen',
        'privacy_ack' => 'Ich habe den Datenschutzhinweis gelesen und mache meine Angaben nach bestem Wissen.',
    ],

    'receipt' => [
        'title' => 'Anfrage eingegangen',
        'headline' => 'Ihre Anfrage ist eingegangen.',
        'number' => 'Aktenzeichen: :nr',
        'mail_sent' => 'An die angegebene Adresse wurde eine Eingangsbestätigung gesendet. Die gesetzliche Bearbeitungsfrist läuft ab dem heutigen Eingang.',
        'back' => 'Zurück zum Formular',
    ],

    'confirmed' => [
        'title' => 'Adresse bestätigt',
        'headline' => 'Vielen Dank — Ihre E-Mail-Adresse ist bestätigt.',
        'text' => 'Die Bestätigung wurde zum Vorgang :nr vermerkt.',
        'no_deadline_effect' => 'Die Bearbeitungsfrist läuft unverändert ab dem Eingang Ihrer Anfrage; die Bestätigung verschiebt sie nicht.',
    ],

    'mail' => [
        'subject' => 'Eingangsbestätigung zu Ihrer Datenschutzanfrage :nr',
        'headline' => 'Ihre Datenschutzanfrage ist eingegangen',
        'intro' => 'Unter dem Aktenzeichen :nr wurde eine Datenschutzanfrage mit dieser E-Mail-Adresse gestellt.',
        'deadline' => 'Die gesetzliche Bearbeitungsfrist läuft ab dem Eingang und endet am :date.',
        'confirm_button' => 'E-Mail-Adresse bestätigen',
        'confirm_note' => 'Die Bestätigung weist nach, dass diese Adresse erreichbar ist. Sie ersetzt nicht die Prüfung Ihrer Identität — dafür meldet sich die verantwortliche Stelle gesondert bei Ihnen. Auf die Frist hat der Klick keinen Einfluss.',
        'not_you' => 'Haben Sie diese Anfrage nicht gestellt, ignorieren Sie diese E-Mail bitte. Eine Auskunft wird ohne Identitätsprüfung nicht erteilt.',
    ],

    'subject' => [
        'email' => 'E-Mail: :value',
        'reference' => 'Aktenzeichen: :value',
    ],

    'internal' => [
        'from_portal' => 'Portal-Eingang',
        'portal_banner' => 'Diese Anfrage kam über das öffentliche Betroffenenportal. Die Identitätsangaben sind ungeprüfte Selbstauskunft.',
        'contact_email' => 'Rückadresse',
        'email_confirmed' => 'bestätigt am :date',
        'email_unconfirmed' => 'nicht bestätigt',
        'identity_required' => 'Vor der Auskunft muss die Identität geprüft und bestätigt werden (Portal-Eingang).',
    ],

    'admin' => [
        'nav' => 'Betroffenenportal',
        'title' => 'Betroffenenportal verwalten',
        'subtitle' => 'Öffentliches Formular für Betroffenenanfragen konfigurieren.',
        'link' => 'Öffentlicher Link',
        'link_hint' => 'Diesen Link veröffentlichen Sie in Ihrer Datenschutzerklärung. Er ist nicht aus dem Organisationsnamen ableitbar.',
        'rotate' => 'Link rotieren',
        'rotate_confirm' => 'Link wirklich rotieren? Bereits veröffentlichte Links werden ungültig.',
        'not_created' => 'Es ist noch kein Betroffenenportal angelegt. Speichern Sie, um eines mit einem zufälligen Link zu erstellen.',
        'settings' => 'Einstellungen',
        'visibility' => 'Sichtbarkeit',
        'is_enabled' => 'Portal aktiv (öffentlich erreichbar)',
        'allow_attachments' => 'Anhänge erlauben',
        'presentation' => 'Darstellung',
        'intro_text' => 'Einleitungstext (optional)',
        'default_locale' => 'Standardsprache (optional, z. B. de)',
        'saved' => 'Betroffenenportal gespeichert.',
        'rotated' => 'Portal-Link wurde rotiert. Bereits veröffentlichte Links sind jetzt ungültig.',
    ],
];
