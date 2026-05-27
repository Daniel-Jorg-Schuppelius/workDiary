<?php

/*
 * scripts/sync-laravel-core-lang.php
 *
 * Holt die englischen Core-Übersetzungen (validation, auth, passwords, pagination)
 * direkt aus dem offiziellen laravel/framework-Repo (GitHub raw) — gepinnt auf
 * die in composer.lock installierte Laravel-Version — und merged sie nach
 * lang/en/.
 *
 * Zweck: Wir vermeiden eine separate Composer-Dependency (siehe laravel-lang/lang
 * Supply-Chain-Vorfall) und haben die Core-Strings unter eigener Kontrolle.
 *
 * Verhalten:
 *   - Default (Dry-Run): zeigt was sich ändern würde (Upstream-Adds vs. Konflikte
 *     vs. lokale Zusätze) ohne zu schreiben.
 *   - --write: deep-merged Upstream + lokale Werte (lokal gewinnt bei Konflikten).
 *     Neue Upstream-Keys werden ergänzt, lokale Erweiterungen bleiben erhalten.
 *   - --replace: ignoriert lokale Werte und überschreibt komplett mit Upstream.
 *     ACHTUNG: Verliert deine lokalen Anpassungen.
 *
 * Sicherheit beim Schreiben:
 *   1. Fetch + HTTP-Status-Check + Retry
 *   2. PHP-Parse-Verifikation (require im Subprozess)
 *   3. Backup nach lang/en/<file>.bak
 *   4. Atomic write (temp file + rename)
 *
 * Usage:
 *   php scripts/sync-laravel-core-lang.php
 *   php scripts/sync-laravel-core-lang.php --write
 *   php scripts/sync-laravel-core-lang.php --replace --write
 *   php scripts/sync-laravel-core-lang.php --version=v13.6.0
 *
 * Exit-Code:
 *   0 — keine Drift bzw. erfolgreich geschrieben
 *   1 — Drift erkannt (Dry-Run)
 *   2 — Fehler (Fetch, Validation, Argumente)
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
const FILES = ['validation.php', 'auth.php', 'passwords.php', 'pagination.php'];
const TARGET_DIR = ROOT . '/lang/en';
const REPO_PATH = 'src/Illuminate/Translation/lang/en';
const FETCH_TIMEOUT = 15;
const FETCH_RETRIES = 2;

// ---------- Argumente ----------
$write = false;
$replace = false;
$versionOverride = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--write' || $arg === '-w') {
        $write = true;
    } elseif ($arg === '--replace') {
        $replace = true;
    } elseif (str_starts_with($arg, '--version=')) {
        $versionOverride = substr($arg, 10);
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, extractHelpHeader());
        exit(0);
    } else {
        fwrite(STDERR, "Unbekanntes Argument: $arg\n");
        exit(2);
    }
}

// ---------- Laravel-Version ----------
$version = $versionOverride ?? detectLaravelVersion();
if ($version === null) {
    fwrite(STDERR, "Konnte Laravel-Version nicht aus composer.lock lesen.\n");
    exit(2);
}

$mode = $replace ? 'REPLACE (lokal wird überschrieben)' : 'MERGE (lokal gewinnt)';
$action = $write ? 'WRITE' : 'dry-run';
fwrite(STDOUT, "Laravel-Version: $version\n");
fwrite(STDOUT, "Modus: $action / $mode\n\n");

// ---------- Pro Datei: fetch, merge/replace, validate, write ----------
$exit = 0;

foreach (FILES as $file) {
    fwrite(STDOUT, "=== $file ===\n");
    $url = sprintf('https://raw.githubusercontent.com/laravel/framework/%s/%s/%s', $version, REPO_PATH, $file);

    $remoteRaw = fetchWithRetry($url, FETCH_RETRIES);
    if ($remoteRaw === null) {
        fwrite(STDERR, "  [FEHLER] Fetch fehlgeschlagen.\n\n");
        $exit = 2;
        continue;
    }

    $remoteData = requireSafe($remoteRaw);
    if ($remoteData === null) {
        fwrite(STDERR, "  [FEHLER] Remote-Datei parst nicht als PHP-Array.\n\n");
        $exit = 2;
        continue;
    }

    $localPath = TARGET_DIR . '/' . $file;
    $localData = is_file($localPath) ? requireSafe((string) file_get_contents($localPath)) : null;

    if ($replace || $localData === null) {
        $resultData = $remoteData;
    } else {
        $resultData = deepMergeLocalWins($remoteData, $localData);
    }

    // Diff-Report
    $remoteFlat = flatten($remoteData);
    $localFlat = $localData !== null ? flatten($localData) : [];
    $resultFlat = flatten($resultData);

    $upstreamAdds = array_diff_key($remoteFlat, $localFlat);
    // Empty-array container keys (e.g. 'attributes' => []) sind kein echter "Add",
    // wenn lokal bereits Sub-Keys (attributes.inhalt, ...) existieren.
    foreach ($upstreamAdds as $k => $v) {
        if (is_array($v) && $v === []) {
            foreach (array_keys($localFlat) as $lk) {
                if (str_starts_with($lk, $k . '.')) {
                    unset($upstreamAdds[$k]);
                    break;
                }
            }
        }
    }
    $upstreamChanges = []; // Keys, die upstream existieren aber lokal anders sind
    foreach ($remoteFlat as $k => $v) {
        if (array_key_exists($k, $localFlat) && $localFlat[$k] !== $v) {
            $upstreamChanges[$k] = ['local' => $localFlat[$k], 'upstream' => $v];
        }
    }
    $localOnly = array_diff_key($localFlat, $remoteFlat);

    if (! $upstreamAdds && ! $upstreamChanges && ! $localOnly && $localData !== null) {
        fwrite(STDOUT, "  [SYNC] keine Unterschiede.\n\n");
        continue;
    }

    if ($localData === null) {
        fwrite(STDOUT, "  [NEW] lokal nicht vorhanden — wird angelegt (" . count($remoteFlat) . " Keys).\n");
    }
    if ($upstreamAdds) {
        fwrite(STDOUT, "  [ADD] " . count($upstreamAdds) . " neue Upstream-Keys:\n");
        foreach (array_slice(array_keys($upstreamAdds), 0, 20) as $k) {
            fwrite(STDOUT, "        + $k\n");
        }
        if (count($upstreamAdds) > 20) {
            fwrite(STDOUT, "        … (+" . (count($upstreamAdds) - 20) . " weitere)\n");
        }
    }
    if ($upstreamChanges) {
        fwrite(STDOUT, "  [CONFLICT] " . count($upstreamChanges) . " Keys upstream geändert (Merge: lokal gewinnt):\n");
        foreach (array_slice($upstreamChanges, 0, 10, true) as $k => $v) {
            fwrite(STDOUT, "        ~ $k\n");
            fwrite(STDOUT, "            local:    " . valuePreview($v['local']) . "\n");
            fwrite(STDOUT, "            upstream: " . valuePreview($v['upstream']) . "\n");
        }
        if (count($upstreamChanges) > 10) {
            fwrite(STDOUT, "        … (+" . (count($upstreamChanges) - 10) . " weitere)\n");
        }
    }
    if ($localOnly && ! $replace) {
        fwrite(STDOUT, "  [KEEP] " . count($localOnly) . " lokale Keys (bleiben erhalten):\n");
        foreach (array_slice(array_keys($localOnly), 0, 10) as $k) {
            fwrite(STDOUT, "        · $k\n");
        }
        if (count($localOnly) > 10) {
            fwrite(STDOUT, "        … (+" . (count($localOnly) - 10) . " weitere)\n");
        }
    }
    if ($localOnly && $replace) {
        fwrite(STDOUT, "  [LOST] " . count($localOnly) . " lokale Keys (werden mit --replace entfernt):\n");
        foreach (array_slice(array_keys($localOnly), 0, 20) as $k) {
            fwrite(STDOUT, "        - $k\n");
        }
    }

    // Schreiben
    if ($write) {
        $rendered = renderPhpArrayFile($resultData);
        // Sanity: rendered output muss parsen
        if (requireSafe($rendered) === null) {
            fwrite(STDERR, "  [FEHLER] Gerendertes Resultat parst nicht — Schreiben abgebrochen.\n\n");
            $exit = 2;
            continue;
        }
        // Skip identical writes
        if (is_file($localPath) && file_get_contents($localPath) === $rendered) {
            fwrite(STDOUT, "  [SKIP] Schreibversion identisch zu lokal — nichts zu tun.\n\n");
            continue;
        }
        if (! writeAtomic($localPath, $rendered)) {
            fwrite(STDERR, "  [FEHLER] Schreiben fehlgeschlagen: $localPath\n\n");
            $exit = 2;
            continue;
        }
        fwrite(STDOUT, "  [DONE] → $localPath (Backup: " . basename($localPath) . ".bak)\n\n");
    } else {
        $exit = max($exit, 1);
        fwrite(STDOUT, "\n");
    }
}

if ($exit === 0) {
    fwrite(STDOUT, $write ? "Core-Lang-Sync abgeschlossen.\n" : "Core-Lang ist in Sync.\n");
} elseif ($exit === 1) {
    fwrite(STDOUT, "Drift erkannt — mit --write übernehmen.\n");
} else {
    fwrite(STDOUT, "Sync mit Fehlern abgebrochen.\n");
}

exit($exit);

// ===================== Helpers =====================

function detectLaravelVersion(): ?string
{
    $lockPath = ROOT . '/composer.lock';
    if (! is_file($lockPath)) {
        return null;
    }
    $lock = json_decode((string) file_get_contents($lockPath), true);
    foreach ($lock['packages'] ?? [] as $pkg) {
        if (($pkg['name'] ?? null) === 'laravel/framework') {
            return $pkg['version'] ?? null;
        }
    }
    return null;
}

function fetchWithRetry(string $url, int $retries): ?string
{
    $lastError = null;
    for ($i = 0; $i <= $retries; $i++) {
        $body = fetch($url, $lastError);
        if ($body !== null) {
            return $body;
        }
        // 4xx ist permanent — kein Retry sinnvoll.
        if ($lastError !== null && preg_match('/^HTTP 4\d\d$/', $lastError)) {
            break;
        }
        if ($i < $retries) {
            fwrite(STDERR, "  [WARN] Fetch-Versuch " . ($i + 1) . " fehlgeschlagen ($lastError) — retry…\n");
            usleep(500_000); // 0.5s backoff
        }
    }
    fwrite(STDERR, "  [FEHLER] Fetch endgültig fehlgeschlagen: $lastError\n");
    return null;
}

function fetch(string $url, ?string &$errorOut = null): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: workDiary-sync-laravel-core-lang\r\nAccept: text/plain\r\n",
            'timeout' => FETCH_TIMEOUT,
            'ignore_errors' => true, // damit wir auch 404-Body lesen können statt nur Warning
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $errorOut = 'network/timeout';
        return null;
    }
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $code = (int) $m[1];
            if ($code === 200) {
                return $body;
            }
            $errorOut = "HTTP $code";
            return null;
        }
    }
    $errorOut = 'no HTTP status header';
    return null;
}

/**
 * Sicheres Parsen eines PHP-Snippets, das `<?php return [...]` enthält.
 * Schreibt nach temp file und ruft php -r 'require ...' im Subprozess auf,
 * um Syntax-Fehler zu erkennen ohne den Parent-Prozess zu töten.
 * Bei Erfolg: gibt das geparste Array zurück. Sonst null.
 */
function requireSafe(string $phpCode): ?array
{
    if (! str_starts_with(ltrim($phpCode), '<?php')) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'lang-sync-') . '.php';
    file_put_contents($tmp, $phpCode);
    try {
        // Erst Syntax-Check
        $checkCmd = escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1';
        exec($checkCmd, $out, $code);
        if ($code !== 0) {
            return null;
        }
        // Dann Wert holen
        $data = @require $tmp;
        return is_array($data) ? $data : null;
    } finally {
        @unlink($tmp);
    }
}

/** Deep-merge: $local wins on conflicts; new keys from $remote are added. */
function deepMergeLocalWins(array $remote, array $local): array
{
    $out = $remote;
    foreach ($local as $k => $v) {
        if (array_key_exists($k, $out) && is_array($out[$k]) && is_array($v)) {
            $out[$k] = deepMergeLocalWins($out[$k], $v);
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

function flatten(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v) && $v !== []) {
            $out = array_merge($out, flatten($v, $key));
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

function valuePreview(mixed $v): string
{
    if (is_array($v)) {
        return '[' . count($v) . ' items]';
    }
    $s = (string) $v;
    return mb_strlen($s) > 80 ? mb_substr($s, 0, 77) . '…' : $s;
}

/** Render an array back into a deterministic PHP file using var_export. */
function renderPhpArrayFile(array $data): string
{
    $export = var_export($data, true);
    // var_export uses "array (...)" syntax; convert to short syntax for readability.
    $export = preg_replace('/array \(/u', '[', $export) ?? $export;
    $export = preg_replace('/^(\s*)\)/m', '$1]', $export) ?? $export;
    $export = preg_replace('/=> \n\s+\[/u', '=> [', $export) ?? $export;
    return "<?php\n\nreturn " . $export . ";\n";
}

/**
 * Atomic write with backup:
 *   1. write to <path>.tmp
 *   2. if original exists: copy original → <path>.bak
 *   3. rename <path>.tmp → <path>
 */
function writeAtomic(string $path, string $content): bool
{
    $tmp = $path . '.tmp';
    $bak = $path . '.bak';
    if (file_put_contents($tmp, $content) === false) {
        return false;
    }
    if (is_file($path)) {
        if (! @copy($path, $bak)) {
            @unlink($tmp);
            return false;
        }
    }
    if (! @rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function extractHelpHeader(): string
{
    $src = (string) file_get_contents(__FILE__);
    if (preg_match('#/\*\s*\n(.*?)\*/#s', $src, $m)) {
        $lines = explode("\n", $m[1]);
        $clean = array_map(fn($l) => preg_replace('/^\s*\*\s?/', '', $l) ?? $l, $lines);
        return trim(implode("\n", $clean)) . "\n";
    }
    return "Siehe Quellcode für Hilfe.\n";
}
