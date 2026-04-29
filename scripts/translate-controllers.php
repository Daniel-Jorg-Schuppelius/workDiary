<?php

/**
 * Wrappt deutsche Strings in `with('success'|'error', '...')` und `withErrors([...])`
 * mit __() in allen Controller-Dateien. Außerdem strings in `validate(['custom'])`
 * werden hier nicht angefasst — Laravel hat dafür eigene Mechanismen.
 *
 * Aufruf: php scripts/translate-controllers.php
 */

$root = __DIR__ . '/..';
$dir = $root . '/app/Http/Controllers';
$jsonFile = $root . '/lang/en.json';

$map = [
    'Bereitschaft angelegt.' => 'Standby created.',
    'Bereitschaft aktualisiert.' => 'Standby updated.',
    'Bereitschaft gelöscht.' => 'Standby deleted.',
    'Notdienst angelegt.' => 'On-call created.',
    'Notdienst aktualisiert.' => 'On-call updated.',
    'Notdienst gelöscht.' => 'On-call deleted.',
    'Mitarbeiter angelegt.' => 'Employee created.',
    'Mitarbeiter aktualisiert.' => 'Employee updated.',
    'Mitarbeiter gelöscht.' => 'Employee deleted.',
    'Mitarbeiter kann nicht gelöscht werden: es sind noch Legacy-Daten vorhanden.' =>
    'Employee cannot be deleted: legacy data still exists.',
    'Eintrag gespeichert.' => 'Entry saved.',
    'Eintrag aktualisiert.' => 'Entry updated.',
    'Eintrag gelöscht.' => 'Entry deleted.',
    'Legacy-Eintrag gespeichert.' => 'Legacy entry saved.',
    'Legacy-Eintrag aktualisiert.' => 'Legacy entry updated.',
    'Legacy-Eintrag gelöscht.' => 'Legacy entry deleted.',
    'Passwort erfolgreich geändert.' => 'Password changed successfully.',
    'Lokales Passwort geändert. Legacy-Passwort konnte nicht synchronisiert werden.' =>
    'Local password changed. Legacy password could not be synchronized.',
    'Aktuelles Passwort ist falsch.' => 'Current password is incorrect.',
    'Unbekannter Modus.' => 'Unknown mode.',
    'Legacy-Modus ist nicht verfügbar (Legacy-DB nicht konfiguriert).' =>
    'Legacy mode is not available (legacy DB not configured).',
    'Legacy-Modus aktiviert.' => 'Legacy mode activated.',
    'Neuer Modus aktiviert.' => 'New mode activated.',
    'Nutzername oder Passwort ist falsch.' => 'Username or password is incorrect.',
    'Legacy-Datenbank ist nicht konfiguriert.' => 'Legacy database is not configured.',
    'Anmeldedaten sind ungültig.' => 'Credentials are invalid.',
    'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.' =>
    'These credentials do not match our records.',
];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$totalReplacements = 0;
$filesChanged = 0;

foreach ($rii as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $original = $content;

    foreach ($map as $de => $en) {
        $deEsc = addcslashes($de, "'");
        // 1) Single-quoted: '...'
        $content = str_replace("'" . $deEsc . "'", "__('" . $deEsc . "')", $content);
        // 2) Double-quoted (selten, aber sicher)
        $deEscD = addcslashes($de, '"');
        $content = str_replace('"' . $deEscD . '"', "__('" . $deEsc . "')", $content);
    }

    if ($content !== $original) {
        $diff = substr_count($content, "__('") - substr_count($original, "__('");
        $totalReplacements += $diff;
        $filesChanged++;
        file_put_contents($path, $content);
        echo "✓ " . str_replace($root . '/', '', $path) . " (+{$diff})\n";
    }
}

echo "\n{$filesChanged} Controller geändert, {$totalReplacements} __()-Aufrufe.\n";

// en.json mergen
$existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
foreach ($map as $de => $en) {
    if (! isset($existing[$de])) $existing[$de] = $en;
}
ksort($existing, SORT_NATURAL | SORT_FLAG_CASE);
file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "lang/en.json: " . count($existing) . " Keys.\n";
