<?php

/**
 * Gleicht die Statusspalte in ../WorkDiary-Architecture/features/README.md
 * gegen den „## Status"-Block der jeweiligen Feature-Datei ab.
 *
 * Hintergrund: Das Register ist die einzige Übersicht über den Projektstand.
 * Läuft es dem tatsächlichen Stand hinterher, unterschätzt es ihn systematisch
 * — beim Abgleich am 2026-08-18 standen 23 fertige Features noch auf
 * „In Progress" oder „Planned". Der Fehler fällt niemandem auf, weil das
 * Register für sich genommen stimmig aussieht.
 *
 * Geprüft wird nur die grobe Stufe (fertig / nicht fertig), nicht der Wortlaut:
 * „Done (API-Pilot offen)" gegen „Umgesetzt, Pilot offen" ist kein Befund.
 *
 * Exit-Code:
 *   0 — Register und Feature-Dateien stimmen überein (oder Repo nicht vorhanden).
 *   1 — Abweichungen; der Bericht nennt sie einzeln.
 *
 * Aufruf: php scripts/check-feature-status.php
 */

declare(strict_types=1);

$features = dirname(__DIR__) . '/../WorkDiary-Architecture/features';

// Das Schwester-Repo ist nicht überall eingebunden (CI, frische Klone) —
// dann gibt es nichts zu prüfen, und das ist kein Fehler.
if (! is_dir($features)) {
    echo "WorkDiary-Architecture nicht vorhanden — Feature-Status nicht geprüft.\n";
    exit(0);
}

$readme = $features . '/README.md';
if (! is_file($readme)) {
    echo "features/README.md fehlt.\n";
    exit(1);
}

/**
 * Gilt dieser Statustext als „fertig"?
 *
 * Auf das Statuswort am Zeilenanfang zu prüfen greift zu kurz: Viele Blöcke
 * nennen erst den Gegenstand („**CalDAV-Publish … umgesetzt**"). Umgekehrt
 * darf ein einleitendes „In Progress" oder „Teilweise umgesetzt" nicht durch
 * ein späteres „umgesetzt" im selben Satz überstimmt werden — deshalb schlägt
 * die Verneinung am Anfang die Bejahung im Satz.
 */
$isDone = static function (string $text): bool {
    $t = mb_strtolower(ltrim($text, '* '));

    foreach (['in progress', 'proposed', 'planned', 'teilweise', 'geplant', 'offen', 'konzept', 'dauerquerschnitt'] as $open) {
        if (str_starts_with($t, $open)) {
            return false;
        }
    }

    // „Kern-Gerüst umgesetzt" ist kein fertiges Feature, sondern der Unterbau
    // dafür — das Wort verneint die Aussage, egal wo im Satz es steht.
    if (str_contains($t, 'gerüst')) {
        return false;
    }

    // Nur der erste Satz zählt; was danach steht, sind Nachträge und Details.
    $firstSentence = mb_substr($t, 0, (int) (mb_strpos($t . '.', '.') ?: mb_strlen($t)));

    return str_contains($firstSentence, 'umgesetzt')
        || str_contains($firstSentence, 'done')
        || str_contains($firstSentence, 'komplett');
};

/** Erste Aussage des „## Status"-Blocks einer Feature-Datei. */
$statusOf = static function (string $path): string {
    $text = (string) file_get_contents($path);
    if (! preg_match('/^## Status\s*\n+(.{0,300})/ms', $text, $m)) {
        return '';
    }

    return trim((string) preg_replace('/\s+/', ' ', $m[1]));
};

/** Ganzer „## Status"-Block (für die Klammer-Heuristik). */
$statusBlockOf = static function (string $path): string {
    $text = (string) file_get_contents($path);
    if (! preg_match('/^## Status\s*\n(.*?)(?=^## |\z)/ms', $text, $m)) {
        return '';
    }

    return mb_strtolower($m[1]);
};

$mismatch = [];
$staleParens = [];
$checked = 0;

foreach (file($readme, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (! preg_match('/^\|\s*P\d\s*\|\s*\[([^\]]+)\]\(\.\/([^)]+)\)\s*\|\s*([^|]*?)\s*\|/u', $line, $m)) {
        continue;
    }

    [, $name, $file, $registerStatus] = $m;
    $path = $features . '/' . $file;
    if (! is_file($path)) {
        $mismatch[] = [$file, $registerStatus, 'Datei fehlt', $name];

        continue;
    }

    $checked++;
    $fileStatus = $statusOf($path);
    if ($fileStatus === '') {
        continue;
    }

    if ($isDone($fileStatus) !== $isDone($registerStatus)) {
        $mismatch[] = [$file, $registerStatus, mb_substr($fileStatus, 0, 110), $name];
    }

    // Klammer-Heuristik (Audit 2026-08): Der Grobstufen-Vergleich oben sieht
    // „Done (X offen)" und „Done" als gleich — die Klammertexte driften daher
    // unbemerkt. Meldet, wenn das Register noch Offenes in der Klammer führt,
    // der Statusblock der Datei aber nichts Offenes mehr kennt. Nur Warnung:
    // der Wortlaut ist bewusst frei.
    if (preg_match('/done\s*\([^)]*offen[^)]*\)/iu', $registerStatus)) {
        $block = $statusBlockOf($path);
        if ($block !== '' && ! str_contains($block, 'offen')) {
            $staleParens[] = [$file, $registerStatus, $name];
        }
    }
}

if ($staleParens !== []) {
    echo "Warnung — Register-Klammern vermutlich veraltet (Datei kennt nichts Offenes mehr):\n";
    foreach ($staleParens as [$file, $register, $name]) {
        echo "- {$name} ({$file}): Register „{$register}\"\n";
    }
    echo "\n";
}

if ($mismatch === []) {
    printf("Feature-Register stimmt: %d Einträge gegen ihre Statusblöcke geprüft.\n", $checked);
    exit(0);
}

echo "# Feature-Status: Register weicht von den Feature-Dateien ab\n\n";
foreach ($mismatch as [$file, $register, $fileStatus, $name]) {
    echo "## {$name}\n";
    echo "- Datei    : {$file}\n";
    echo "- Register : {$register}\n";
    echo "- Statusblock: {$fileStatus}\n\n";
}
printf("Abweichungen: %d von %d.\n", count($mismatch), $checked);

exit(1);
