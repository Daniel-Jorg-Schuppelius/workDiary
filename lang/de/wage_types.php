<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : wage_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Lohnarten & Export-Lieferung",
        'index_subtitle' => "Interne Lohnarten auf die Lohnartennummern des Ziel-Lohnprogramms abbilden und die automatische Lieferung je Export-Profil konfigurieren.",
        'mappings_help' => "Wie funktioniert das Lohnarten-Mapping?",
        'mappings_help_text' => "Beim Zeit-Export wird die Lohnart jeder Zeile zuerst über dieses Mapping aufgelöst, danach über die Lohnart der Zuschlagsregel; Normalstunden ohne Zuordnung behalten die Standard-Lohnart des Profils. Fehlt für eine Zuschlags- oder Abwesenheitszeile jede Zuordnung, bricht der Export mit einer Fehlermeldung ab, statt eine fehlerhafte Datei zu erzeugen.",
        'create' => "Lohnart-Zuordnung anlegen",
        'edit' => "Lohnart-Zuordnung bearbeiten",
        'empty' => "Keine Lohnart-Zuordnungen vorhanden — die Standard-Lohnarten der Profile bleiben wirksam.",
        'delivery' => "Automatische Lieferung",
        'delivery_help_text' => "Fertige Exporte werden je Profil automatisch per E-Mail und/oder SFTP an das Lohnbüro geliefert; der Nachweis (wann/wohin) steht am Export.",
        'delivery_edit' => "Lieferung konfigurieren — :profile",
    ],

    'field' => [
        'basics' => "Zuordnung",
        'profile' => "Export-Profil",
        'wage_type' => "Interne Lohnart",
        'wage_type_help' => "Standard-Lohnarten des Zeit-Exports plus die Zuschlagsarten Ihrer Organisation.",
        'external_code' => "Ziel-Lohnart (extern)",
        'external_code_help' => "Lohnartennummer im Ziel-Lohnprogramm — für DATEV/Lexware numerisch mit bis zu 4 Stellen.",
        'standard_types' => "Standard-Lohnarten",
        'surcharge_types' => "Zuschlagsarten (Organisation)",
        'choose' => "– bitte wählen –",
        'mail' => "E-Mail-Versand",
        'mail_toggle' => "Export-Datei nach Abschluss per E-Mail versenden",
        'mail_recipients' => "Empfänger",
        'mail_recipients_help' => "Mehrere Adressen mit Komma, Semikolon oder Zeilenumbruch trennen.",
        'sftp' => "SFTP-Upload",
        'sftp_toggle' => "Export-Datei nach Abschluss per SFTP hochladen",
        'sftp_host' => "Host",
        'sftp_port' => "Port",
        'sftp_username' => "Benutzername",
        'sftp_password' => "Passwort",
        'sftp_password_help' => "Leer lassen, um das gespeicherte Passwort zu behalten.",
        'sftp_root' => "Zielverzeichnis",
        'sftp_root_help' => "Leer = Home-Verzeichnis des SFTP-Benutzers.",
        'enabled' => "Aktiv",
        'disabled' => "Aus",
    ],

    'action' => [
        'create' => "Anlegen",
        'edit' => "Bearbeiten",
        'save' => "Speichern",
        'delete' => "Löschen",
        'delete_confirm' => "Lohnart-Zuordnung wirklich löschen? Bestehende Exporte bleiben unverändert; künftige Exporte nutzen wieder die Standard-Lohnart.",
        'configure' => "Konfigurieren",
    ],

    'flash' => [
        'created' => "Lohnart-Zuordnung angelegt.",
        'updated' => "Lohnart-Zuordnung aktualisiert.",
        'deleted' => "Lohnart-Zuordnung gelöscht.",
        'delivery_saved' => "Lieferkonfiguration gespeichert.",
    ],

    'validation' => [
        'external_code_format' => "Die Ziel-Lohnart hat kein gültiges Format für das gewählte Export-Profil (DATEV/Lexware: numerisch, 1–4 Stellen).",
        'wage_type_unique' => "Für diese Lohnart existiert in diesem Profil bereits eine Zuordnung.",
        'recipients_required' => "Für den E-Mail-Versand wird mindestens eine Empfängeradresse benötigt.",
        'password_required' => "Für den SFTP-Upload wird ein Passwort benötigt.",
    ],

    'error' => [
        'missing_mappings' => "Export abgebrochen: Für folgende Lohnarten fehlt die Ziel-Lohnart im Ziel-Lohnprogramm: :types. Bitte unter „Lohnarten & Export-Lieferung“ eine Zuordnung pflegen oder die Lohnart an der Zuschlagsregel hinterlegen.",
    ],

    'delivery' => [
        'title_evidence' => "Automatische Lieferung",
        'evidence_mail' => "E-Mail an :to",
        'evidence_sftp' => "SFTP nach :target",
        'note_auto' => "Automatisch geliefert (:channels).",
        'file_missing' => "Export-Datei nicht gefunden — Lieferung übersprungen.",
        'abandoned' => "Automatische Lieferung nach mehreren Versuchen endgültig fehlgeschlagen.",
    ],

    'mail' => [
        'subject' => "Zeit-Export :profile :period",
        'heading' => "Zeit-Export für die Lohnabrechnung",
        'body' => "Im Anhang finden Sie den Zeit-Export des Profils :profile für den Zeitraum :period.",
        'meta' => ":rows Zeilen · SHA-256 :hash",
    ],
];
