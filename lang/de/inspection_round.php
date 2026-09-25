<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_round.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfmittelrunden (Feature 075, MVP-899).
return [
    'nav' => 'Prüfrunden',
    'title' => 'Prüfmittelrunden',
    'subtitle' => 'Soll-Liste fälliger Prüfungen eines Standorts oder einer Gruppe — Objekt scannen, Ergebnis erfassen.',
    'open' => 'Runde anlegen',
    'show' => 'Runde öffnen',
    'name' => 'Bezeichnung',
    'due_until' => 'Fällig bis',
    'progress' => 'Erledigt',
    'status' => 'Status',
    'none_title' => 'Noch keine Prüfrunden',
    'none' => 'Legen Sie eine Runde für einen Standort oder eine Gruppe an.',
    'location' => 'Standort',
    'category' => 'Gruppe (Kategorie)',
    'profile' => 'Prüfprofil',
    'customer' => 'Kunde',
    'any' => 'Alle',
    'form_hint' => 'Die Runde übernimmt alle aktiven Prüfpflichten, die bis zum Datum fällig sind. Später fällige kommen nicht hinzu.',
    'empty' => 'Für diese Auswahl ist bis zum Datum keine Prüfung fällig.',
    'opened' => 'Runde mit :count Prüfungen angelegt.',
    'scan' => 'Objekt scannen',
    'scan_submit' => 'Öffnen',
    'scan_unknown' => 'Unbekannter Objekt-Code.',
    'scan_not_in_round' => '„:asset“ gehört nicht zu dieser Runde.',
    'scan_done' => 'Alle Prüfungen dieses Objekts sind in der Runde erledigt.',
    'scan_several' => 'Für dieses Objekt sind mehrere Prüfungen offen:',
    'kpi_done' => 'Erledigt',
    'kpi_missing' => 'Fehlend',
    'kpi_overdue' => 'Überfällig',
    'asset' => 'Objekt',
    'due_on' => 'Fällig am',
    'overdue' => 'Überfällig',
    'pending' => 'Offen',
    'capture' => 'Prüfung erfassen',
    'capture_submit' => 'Prüfung speichern',
    'result' => 'Ergebnis',
    'note' => 'Bemerkung',
    'signature_name' => 'Unterschrift (Name)',
    'certificate_hint' => 'Dieses Prüfprofil verlangt für „bestanden“ einen Zertifikatsnachweis. Erfassen Sie die Prüfung dann im Prüfkalender.',
    'recorded' => 'Prüfung dokumentiert.',
    'close' => 'Runde abschließen',
    'confirm_close' => 'Runde abschließen? :missing Prüfungen sind noch offen und bleiben als fehlend stehen.',
    'closed' => 'Die Runde ist abgeschlossen.',
    'closed_flash' => 'Runde abgeschlossen.',
    'already_done' => 'Diese Prüfung ist in der Runde bereits erfasst.',
];
