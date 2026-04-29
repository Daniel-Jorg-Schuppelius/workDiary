<?php

/**
 * One-shot Übersetzungs-Helper:
 *  - Wendet ein DE→__('DE') Mapping auf alle Blade-Templates unter resources/views an.
 *  - Ersetzt nur dann, wenn der Treffer eindeutig in einem HTML-Textknoten steht
 *    (>Text< oder als Attribut-Wert title="Text" / aria-label="Text" / placeholder="Text").
 *  - Aktualisiert lang/en.json mit allen Keys.
 *
 * Aufruf:  php scripts/translate-views.php
 */

$root = __DIR__ . '/..';
$viewsDir = $root . '/resources/views';
$jsonFile = $root . '/lang/en.json';

// Map: DE-Original  =>  EN-Übersetzung
$map = [
    // Allgemein
    'Speichern' => 'Save',
    'Abbrechen' => 'Cancel',
    'Schließen' => 'Close',
    'Löschen'   => 'Delete',
    'Bearbeiten' => 'Edit',
    'Details'   => 'Details',
    'Aktion'    => 'Action',
    'Aktionen'  => 'Actions',
    'Filtern'   => 'Filter',
    'Filter zurücksetzen' => 'Reset filter',
    'Zurücksetzen' => 'Reset',
    'Zurück'    => 'Back',
    'Anwenden'  => 'Apply',
    'Heute'     => 'Today',
    'Vorwoche'  => 'Previous week',
    'Nächste Woche' => 'Next week',
    'Wochenplan' => 'Weekly schedule',
    'Wochenansicht' => 'Week view',
    'Arbeitsliste' => 'Work list',
    'Übersicht' => 'Overview',
    'Notdienstplan' => 'On-call schedule',
    'Erstellen' => 'Create',
    'Anlegen'   => 'Create',
    'Aktualisieren' => 'Update',
    'Bestätigen' => 'Confirm',
    'Ja'        => 'Yes',
    'Nein'      => 'No',
    'Jetzt'     => 'Now',
    'Mehr'      => 'More',
    'Weniger'   => 'Less',
    'Optional'  => 'Optional',
    'Pflichtfeld' => 'Required',

    // Status
    'Status'    => 'Status',
    'Offen'     => 'Open',
    'Probleme'  => 'Problems',
    'Problem'   => 'Problem',
    'Bestätigt' => 'Confirmed',
    'Erledigt'  => 'Done',
    'Alle'      => 'All',
    'Gesamt'    => 'Total',
    'Unbekannt' => 'Unknown',

    // Felder
    'Mitarbeiter' => 'Employee',
    'Inhalt'    => 'Content',
    'Antwort'   => 'Reply',
    'Von'       => 'From',
    'Bis'       => 'To',
    'Von – Bis' => 'From – To',
    'Zeitraum'  => 'Period',
    'Ab heute'  => 'From today',
    'Bis heute' => 'Until today',
    'Nur meine' => 'Only mine',
    'Datum'     => 'Date',
    'Uhrzeit'   => 'Time',
    'Name'      => 'Name',
    'Nutzername' => 'Username',
    'Benutzername' => 'Username',
    'Passwort'  => 'Password',
    'Passwort wiederholen' => 'Repeat password',
    'Aktuelles Passwort' => 'Current password',
    'Neues Passwort' => 'New password',
    'E-Mail'    => 'Email',
    'Rolle'     => 'Role',
    'Admin'     => 'Admin',
    'Nutzer'    => 'User',
    'Beschreibung' => 'Description',
    'Notiz'     => 'Note',
    'Notizen'   => 'Notes',
    'Datei'     => 'File',
    'Dateien'   => 'Files',

    // Navigation/Module
    'Tagebuch'  => 'Diary',
    'Bereitschaft & Notdienst' => 'Standby & On-call',
    'Bereitschaft' => 'Standby',
    'Notdienst' => 'On-call',
    'Archiv'    => 'Archive',
    'Archivliste' => 'Archive list',
    'Callcenter' => 'Call center',
    'Eintrag'   => 'Entry',
    'Einträge'  => 'Entries',
    'Aufträge'  => 'Tasks',

    // Header/Buttons
    '+ Neuer Eintrag' => '+ New entry',
    'Neuer Eintrag'   => 'New entry',
    'Eintrag bearbeiten' => 'Edit entry',
    'Eintrag anlegen' => 'Create entry',
    'Bereitschaft anlegen' => 'Create standby',
    'Notdienst anlegen' => 'Create on-call',
    'Mitarbeiter anlegen' => 'Create employee',
    'Mitarbeiter bearbeiten' => 'Edit employee',
    'Passwort ändern' => 'Change password',
    'Passwort speichern' => 'Save password',
    'Abmelden'  => 'Sign out',
    'Anmelden'  => 'Sign in',
    'Navigation' => 'Navigation',
    'Legacy'    => 'Legacy',
    'Neu'       => 'New',
    'Farbschema wechseln' => 'Toggle color scheme',
    'Sprache wechseln' => 'Switch language',

    // Bulk
    'Bulk-Aktionen' => 'Bulk actions',
    'Aktion wählen…' => 'Choose action…',
    'Status → Offen' => 'Status → Open',
    'Status → Problem' => 'Status → Problem',
    'Status → Bestätigt' => 'Status → Confirmed',
    'Status → Erledigt' => 'Status → Done',
    'Auswählen' => 'Select',
    'Alle auswählen' => 'Select all',

    // Callcenter / Notdienst
    'Notdienst aktuell' => 'Current on-call',
    'Bereitschaft aktuell' => 'Current standby',
    'Heute kein Notdienst eingetragen.' => 'No on-call scheduled for today.',
    'Heute keine Bereitschaft eingetragen.' => 'No standby scheduled for today.',
    'Offene & problematische Einträge' => 'Open & problematic entries',
    'Keine offenen Einträge.' => 'No open entries.',
    'Eingeloggt als' => 'Signed in as',
    'Schicht'    => 'Shift',

    // Empty/Hinweise
    'Keine Legacy-Einträge gefunden.' => 'No legacy entries found.',
    'Keine Einträge gefunden.' => 'No entries found.',
    'Keine Mitarbeiter gefunden.' => 'No employees found.',
    'Keine Daten vorhanden.' => 'No data available.',

    // Footer
    'Alle Rechte vorbehalten.' => 'All rights reserved.',

    // Labels/Headings noch
    'Aktion bestätigen' => 'Confirm action',
    'Angemeldet bleiben' => 'Stay signed in',
    'Rückmeldung' => 'Reply',
    'Erstellt'    => 'Created',
    'Geändert'    => 'Modified',
    'Aktuell'     => 'Current',
    'Archiv bis'  => 'Archive until',
    'Notdienstplan Login' => 'On-call schedule login',
    'Kalenderwoche' => 'Calendar week',
    'Woche wählen' => 'Choose week',
    'Legacy Eintrag' => 'Legacy entry',
    'Daten ab heute' => 'Data from today',
    'Daten bis heute' => 'Data until today',
    'Ungelesen'   => 'Unread',
    'In Bearbeitung' => 'In progress',
    'Altes Passwort' => 'Old password',
    'Operations Dashboard' => 'Operations dashboard',
    'Aktiver Modus' => 'Active mode',
    'Datenquelle' => 'Data source',
    'Sofort in Bearbeitung nehmen' => 'Start working immediately',
    'Eskalationen mit Handlungsbedarf' => 'Escalations requiring action',
    'Team'        => 'Team',
    'Verfügbare Mitarbeitende' => 'Available employees',
    'Prioritäten' => 'Priorities',
    'Jetzt wichtig' => 'Important now',
    'Aktuelle Arbeitslage' => 'Current workload',
    'Produktzugang erforderlich' => 'Product access required',
    'Heute arbeiten' => 'Work today',
];

// Pattern: schützt vor Doppel-Übersetzung indem __( bereits umgebene Strings ausgeschlossen werden.
function escapeForHtml(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function bladeEscape(string $s): string {
    // Für die Verwendung innerhalb von __('...') in Blade
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
$totalReplacements = 0;
$filesChanged = 0;

foreach ($rii as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $original = $content;

    foreach ($map as $de => $en) {
        $deH = escapeForHtml($de);
        $deB = bladeEscape($de);

        // 1) >Text< (HTML-Textknoten, an Wortgrenzen)
        $pattern = '/(?<=>)[\s]*' . preg_quote($deH, '/') . '[\s]*(?=<)/u';
        $content = preg_replace_callback($pattern, function ($m) use ($de, $deB) {
            return "{{ __('" . $deB . "') }}";
        }, $content);

        // 2) title="Text" / aria-label="Text" / placeholder="Text" / alt="Text"
        $attrPattern = '/(\s(?:title|aria-label|placeholder|alt)=)"' . preg_quote($deH, '/') . '"/u';
        $content = preg_replace_callback($attrPattern, function ($m) use ($deB) {
            return $m[1] . '"{{ __(\'' . $deB . '\') }}"';
        }, $content);
    }

    if ($content !== $original) {
        $diff = substr_count($content, "__('") - substr_count($original, "__('");
        $totalReplacements += $diff;
        $filesChanged++;
        file_put_contents($path, $content);
        echo "✓ " . str_replace($root . '/', '', $path) . " (+{$diff})\n";
    }
}

echo "\n{$filesChanged} Dateien geändert, {$totalReplacements} neue __()-Aufrufe.\n";

// en.json aktualisieren
$existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
foreach ($map as $de => $en) {
    if (! isset($existing[$de])) $existing[$de] = $en;
}
ksort($existing, SORT_NATURAL | SORT_FLAG_CASE);
file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "lang/en.json aktualisiert (" . count($existing) . " Keys).\n";
