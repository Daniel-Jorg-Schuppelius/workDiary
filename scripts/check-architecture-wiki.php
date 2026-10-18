<?php

/**
 * Lässt den Wiki-Lint des Schwester-Repos WorkDiary-Architecture laufen
 * (scripts/wiki-lint.php): veralteter Index, tote Links, Datum im Namen einer
 * Seite mit gültigem Stand.
 *
 * Hintergrund: Ein Katalog, den niemand prüft, veraltet mit der ersten neuen
 * Seite. Das Gate hängt den Lint an `composer qa`, so wie `features:check`
 * das Feature-Register prüft. Warnungen des Lints (Seitengröße, fehlende
 * Querverweise) bleiben Sache des Doku-Repos und brechen hier nicht.
 *
 * Exit-Code:
 *   0 — Lint ohne Fehler (oder Schwester-Repo nicht vorhanden).
 *   1 — Fehler; die Ausgabe des Lints nennt sie einzeln.
 *
 * Aufruf: php scripts/check-architecture-wiki.php
 */

declare(strict_types=1);

$lint = dirname(__DIR__) . '/../WorkDiary-Architecture/scripts/wiki-lint.php';

// Das Schwester-Repo ist nicht überall eingebunden (CI, frische Klone) —
// dann gibt es nichts zu prüfen, und das ist kein Fehler.
if (! is_file($lint)) {
    echo "WorkDiary-Architecture nicht vorhanden — Wiki nicht geprüft.\n";
    exit(0);
}

passthru(sprintf('%s %s --no-warnings', escapeshellarg(PHP_BINARY), escapeshellarg($lint)), $exitCode);

exit($exitCode === 0 ? 0 : 1);
