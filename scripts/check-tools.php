<?php

/**
 * Meldet Systemwerkzeuge, die die Toolkits und die App brauchen, auf diesem
 * Host aber fehlen (pdftotext, tesseract, ocrmypdf, java, …).
 *
 * Gelesen werden config/*executables.json der App und die *executables.json
 * der Pakete unter vendor/ — dieselben Dateien, aus denen
 * installscript/install-dependencies.sh die apt-Pakete installiert. Die
 * Verfügbarkeit entscheidet der ConfigLoader des config-toolkit, also genau
 * die Auflösung, mit der die Toolkits zur Laufzeit einen Eintrag als „nicht
 * verfügbar“ laden und die abhängige Funktion abschalten.
 *
 * deploy.sh ruft die Prüfung nach dem Installer auf; ohne Root-Rechte (Deploy
 * als Web-User) ist sie das Einzige, was der Deploy zu den Werkzeugen tun
 * kann — der Bericht nennt dann, was der Betreiber als root nachholen muss.
 *
 * Exit-Code:
 *   0 — alle erforderlichen Werkzeuge vorhanden
 *   1 — mindestens ein erforderliches Werkzeug fehlt (der Bericht nennt sie)
 *   2 — Aufruffehler (vendor/autoload.php fehlt, keine Konfiguration gefunden)
 *
 * Aufruf: php scripts/check-tools.php [--all] [--app-dir=PFAD]
 *   --all          auch optionale Werkzeuge (required=false) melden
 *   --app-dir=PFAD Anwendungsverzeichnis (Default: Elternordner dieses Skripts)
 */

declare(strict_types=1);

$appDir = dirname(__DIR__);
$all = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--all') {
        $all = true;
    } elseif (str_starts_with($arg, '--app-dir=')) {
        $appDir = substr($arg, strlen('--app-dir='));
    } else {
        fwrite(STDERR, "Unbekanntes Argument: {$arg}\n");
        exit(2);
    }
}

$appDir = rtrim($appDir, '/');
// Das Toolkit kommt aus dem geprüften Deployment; fehlt es dort (anderes
// --app-dir, Test), aus dem Projekt dieses Skripts.
$autoload = null;
foreach ([$appDir, dirname(__DIR__)] as $candidate) {
    if (is_file($candidate . '/vendor/autoload.php')) {
        $autoload = $candidate . '/vendor/autoload.php';
        break;
    }
}
if ($autoload === null) {
    fwrite(STDERR, "vendor/autoload.php fehlt unter {$appDir} — erst composer install.\n");
    exit(2);
}
require $autoload;

// Der Loader meldet jedes fehlende erforderliche Werkzeug als ERROR — hier
// IST der Bericht die Ausgabe, das Log bliebe nur Echo.
\ERRORToolkit\LoggerRegistry::setLogger(new \Psr\Log\NullLogger);

// Reihenfolge wie im Installer: die App-Konfiguration zuerst, dann vendor/.
// Deckt ein früherer Eintrag ein Programm ab (java → default-jre-headless),
// zählt ein späteres Paket für dasselbe Programm nicht mehr (default-jre).
$files = array_merge(
    glob($appDir . '/config/*executables.json') ?: [],
    glob($appDir . '/vendor/*/*/config/*executables.json') ?: [],
);
if ($files === []) {
    fwrite(STDERR, "Keine *executables.json unter {$appDir} gefunden.\n");
    exit(2);
}

/** @var array<string, array{programs: array<string, true>, sources: array<string, true>, required: bool}> $missing je Paket */
$missing = [];
/** @var array<string, string> $covered Programm → Paket, das es bereits abdeckt */
$covered = [];

foreach ($files as $file) {
    $raw = json_decode((string) file_get_contents($file), true);
    if (! is_array($raw)) {
        fwrite(STDERR, "Ungültiges JSON: {$file}\n");
        continue;
    }

    $loader = \ConfigToolkit\ConfigLoader::create();
    if (! $loader->loadConfigFile($file)) {
        fwrite(STDERR, "Nicht ladbar: {$file}\n");
        continue;
    }

    $source = str_starts_with($file, $appDir . '/vendor/')
        ? basename(dirname($file, 2))
        : substr($file, strlen($appDir) + 1);

    foreach ($raw as $section => $entries) {
        if (! is_array($entries)) {
            continue;
        }
        $parsed = $loader->get($section);

        foreach ($entries as $name => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $resolved = is_array($parsed) && is_array($parsed[$name] ?? null) ? $parsed[$name] : [];
            if (! empty($resolved['path'])) {
                continue;
            }
            $required = filter_var($entry['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $required && ! $all) {
                continue;
            }

            $program = (string) ($entry['linuxPath'] ?? $entry['path'] ?? $name);
            $package = is_string($entry['package'] ?? null) && $entry['package'] !== ''
                ? $entry['package']
                : null;

            if ($package === null) {
                // JARs und Installer-Objekte lädt install-dependencies.sh selbst herunter.
                $package = '(kein apt-Paket, lädt install-dependencies.sh)';
            } elseif (isset($covered[$program]) && $covered[$program] !== $package) {
                $package = $covered[$program];
            }
            $covered[$program] ??= $package;

            $missing[$package] ??= ['programs' => [], 'sources' => [], 'required' => false];
            $missing[$package]['programs'][$program] = true;
            $missing[$package]['sources'][$source] = true;
            $missing[$package]['required'] = $missing[$package]['required'] || $required;
        }
    }
}

if ($missing === []) {
    echo $all
        ? "Alle konfigurierten Systemwerkzeuge sind vorhanden.\n"
        : "Alle erforderlichen Systemwerkzeuge sind vorhanden.\n";
    exit(0);
}

$requiredPackages = array_keys(array_filter($missing, fn (array $m): bool => $m['required']));
$optionalPackages = array_keys(array_filter($missing, fn (array $m): bool => ! $m['required']));
$width = max(array_map('strlen', array_keys($missing)));

$print = function (array $packages) use ($missing, $width): void {
    foreach ($packages as $package) {
        printf(
            "  %-{$width}s  %s  (%s)\n",
            $package,
            implode(', ', array_keys($missing[$package]['programs'])),
            implode(', ', array_keys($missing[$package]['sources'])),
        );
    }
};

if ($requiredPackages !== []) {
    echo "Fehlende Systemwerkzeuge (erforderlich — abhängige Funktionen sind abgeschaltet):\n";
    $print($requiredPackages);
}
if ($optionalPackages !== []) {
    echo "Fehlende optionale Werkzeuge:\n";
    $print($optionalPackages);
}

$apt = array_values(array_filter(
    array_merge($requiredPackages, $optionalPackages),
    fn (string $p): bool => ! str_starts_with($p, '('),
));
if ($apt !== []) {
    echo 'Pakete: apt-get install -y ' . implode(' ', $apt) . "\n";
}

exit($requiredPackages === [] ? 0 : 1);
