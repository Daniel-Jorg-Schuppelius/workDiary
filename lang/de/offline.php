<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Offline-Sync (Feature 035): Offline-Änderungs-Seite + Statusanzeige.
return [
    'title' => 'Offline-Änderungen',
    'subtitle' => 'Offline erfasste Aktionen — ausstehend, im Konflikt oder abgelehnt.',
    'notice' => 'Ausstehende und abgelehnte Einträge liegen nur auf diesem Gerät; ausstehende werden automatisch übertragen, sobald eine Verbindung besteht. Konflikte brauchen Ihre Entscheidung: Der Bestand hat sich seit der Erfassung geändert (etwa die Stempelung oder ein inzwischen abgeschlossener Tag) oder eine Pflichtangabe fehlt. Offene Konflikte erscheinen hier auch, wenn sie auf einem anderen Gerät entstanden sind.',
    'empty' => 'Keine offline erfassten Änderungen vorhanden.',
    'section' => [
        'pending' => 'Ausstehend',
        'rejected' => 'Abgelehnt',
        'conflict' => 'Konflikte',
    ],
    'type' => [
        'clock_in' => 'Einstempeln',
        'clock_out' => 'Ausstempeln',
        'comment' => 'Auftrags-Kommentar',
        'form' => 'Formular',
        'attendance_correct' => 'Stempelzeit-Korrektur',
        'time_entry' => 'Projektzeit',
        'material_usage' => 'Material',
        'learning_unit' => 'Lerneinheit abgeschlossen',
        'inventory_count' => 'Inventur-Scan',
        'tour_arrive' => 'Tourstopp angekommen',
        'tour_depart' => 'Tourstopp abgefahren',
        'patrol_scan' => 'Kontrollpunkt',
        'takeoff_line' => 'Aufmaßzeile',
    ],
    'action' => [
        'retry' => 'Erneut anwenden',
        'discard' => 'Verwerfen',
        'take_server' => 'Fremden Stand übernehmen',
        'force_local' => 'Meine Fassung senden',
        'edit' => 'Bearbeiten',
        'send_edited' => 'Bearbeitet senden',
        'cancel_edit' => 'Abbrechen',
    ],
    'conflict_hint' => 'Serverstand: :server',
    'photos_queued' => 'Fotos in der Warteschlange: :count',
    // Konflikte entscheiden (MVP-1595).
    'field' => [
        'missing' => 'Es fehlt',
        'lock' => 'Sperre',
        'stock' => 'Lager',
    ],
    // Konflikt aus fehlendem Bestand (MVP-1691): nur mit Recht „Negative Bestände freigeben“.
    'allow_negative_hint' => 'Bucht den Verbrauch trotz fehlendem Bestand in den Negativbestand. Verlangt Ihre Organisation eine zweite Freigabe, entsteht ein Antrag im Genehmigungs-Eingang.',
    'remote_hint' => 'Auf einem anderen Gerät erfasst — die Inhalte liegen dort. Hier können Sie den Konflikt nur verwerfen.',
    'resolve_failed' => 'Die Entscheidung konnte nicht übertragen werden. Bitte versuchen Sie es mit Verbindung erneut.',
    'unresolved' => 'offen',
    'flash' => [
        'resolved' => 'Konflikt entschieden.',
        'not_found' => 'Diesen Konflikt gibt es nicht mehr.',
    ],
];
