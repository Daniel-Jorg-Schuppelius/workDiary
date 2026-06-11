<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metrics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Betriebsmetriken',
    ],

    'subtitle' => 'Technische Kennzahlen und aggregierte Feature-Nutzung dieser Installation.',

    'privacy_notice' => 'Alle Kennzahlen werden ausschließlich lokal erhoben und gespeichert. Es findet kein externes Senden statt; die Feature-Nutzung wird nur als Tages-Aggregat je Organisation gezählt — ohne Personenbezug und ohne fachliche Inhalte.',

    'section' => [
        'queue' => 'Queue',
        'backups' => 'Backup-Heartbeats',
        'plugin_errors' => 'Plugin-Fehler (7 Tage)',
        'storage' => 'Speicher',
        'active_users' => 'Aktive Benutzer (30 Tage)',
        'module_counts' => 'Datensätze je Kernmodul',
        'feature_usage' => 'Feature-Nutzung (30 Tage)',
    ],

    'field' => [
        'version' => 'Version',
        'queue_pending' => 'Wartende Jobs',
        'queue_failed' => 'Fehlgeschlagene Jobs',
        'attachments' => 'Anhänge',
        'document_versions' => 'Dokument-Versionen',
        'feature' => 'Feature',
        'usage_total' => 'Anzahl',
        'last_used_on' => 'Zuletzt genutzt',
    ],

    'module' => [
        'diary_entries' => 'Aufträge (Tagebuch)',
        'protocols' => 'Protokolle',
        'documents' => 'Dokumente',
        'form_submissions' => 'Formulare (ausgefüllt)',
        'knowledge_articles' => 'Wissensartikel',
        'communication_notes' => 'Kommunikationsnotizen',
    ],

    'empty' => [
        'queue' => 'Keine Queue-Tabellen vorhanden (Sync-Driver).',
        'backups' => 'Noch keine Backup-Heartbeats empfangen.',
        'plugin_errors' => 'Keine Plugin-Fehler in den letzten 7 Tagen.',
        'active_users' => 'Keine Login-Daten verfügbar.',
        'feature_usage' => 'Noch keine Feature-Nutzung erfasst.',
    ],

    'hint' => [
        'storage_db_metadata' => 'Anzahl und Größe laut Datenbank-Metadaten (kein Dateisystem-Scan — Disk-Belegung zeigt die Diagnose-Seite).',
        'active_users' => 'Eindeutige Benutzer mit Login in den letzten 30 Tagen (Quelle: Audit-Log).',
        'feature_usage_window' => 'Aggregiert je Organisation und Tag über die letzten 30 Tage. Daten bleiben lokal.',
    ],

    'generated_at' => 'Erzeugt: :at',
];
