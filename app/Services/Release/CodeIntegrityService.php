<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CodeIntegrityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Release;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\IntegrityCheckStatus;
use App\Models\{AuditLog, IntegrityCheck, User};
use App\Notifications\GenericEventNotification;
use App\Services\Isms\SbomGenerator;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Facades\{Notification, Storage};

/**
 * Datei-Hash-Baseline des Quelltexts (Feature 095, MVP-439/440):
 * Eigencode dateiweise (SHA-256), vendor/ als Aggregat je Composer-Paket,
 * Root-Hash über das kanonische JSON — der Root wandert über
 * {@see ReleaseManifestService} in das Ed25519-signierte release.json
 * (Kette: Signatur → release.json → integrity.json → jede Datei).
 *
 * Verzeichnis-Walk bewusst app-lokal (Klasse F): Toolkit-`Files::get`
 * folgt Symlink-Verzeichnissen (public/storage → Uploads) und kennt keine
 * Ausschlüsse beim Traversieren.
 */
class CodeIntegrityService {
    public const STORAGE_PATH = 'release/integrity.json';

    public const SCHEMA = 'workdiary.integrity-manifest/v1';

    /** Pseudo-Paket für vendor-Root (autoload.php, composer/, bin/). */
    private const AUTOLOADER_PACKAGE = 'composer-autoloader';

    /**
     * Baut das Integritäts-Manifest über den konfigurierten Scan-Umfang.
     *
     * @return array<string, mixed>
     */
    public function build(string $source = 'release', ?int $createdBy = null): array {
        $files = $this->scanFiles();
        $packages = $this->scanPackages();

        return [
            'schema' => self::SCHEMA,
            'generated_at' => now()->toIso8601String(),
            'source' => $source,
            'created_by' => $createdBy,
            'application' => [
                'version' => (string) config('app.version', '0.1.0-dev'),
                'build' => app(SbomGenerator::class)->resolveGitHash(),
            ],
            'files' => $files,
            'packages' => $packages,
            'root' => $this->rootHash($files, $packages),
            // `.env`-Drift-Signal (MVP-447): datensparsam — Datei-Hash plus
            // Hash der SORTIERTEN Schlüsselnamen, nie Werte. Nur bei lokaler
            // Baseline sinnvoll; Release-Baselines kennen die .env nicht.
            'env' => $source === 'local' ? $this->envFingerprint() : null,
        ];
    }

    /**
     * Fingerabdruck der `.env`: sha256 der Datei + sha256 der sortierten
     * Schlüsselnamen. Damit lassen sich „Werte geändert" und
     * „Schlüsselsatz geändert" unterscheiden, ohne ein Secret zu lesen oder
     * zu speichern.
     *
     * @return array{file: string, keys: string, key_count: int}|null
     */
    public function envFingerprint(): ?array {
        // Dieselbe Wurzel wie der Datei-Scan (`integrity.base`-Override).
        $path = $this->basePath() . DIRECTORY_SEPARATOR . '.env';
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);
        $keys = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            $keys[] = trim(strtok($line, '=') ?: '');
        }
        $keys = array_values(array_unique(array_filter($keys)));
        sort($keys);

        return [
            'file' => CryptoHelper::hash($contents),
            'keys' => CryptoHelper::hash(implode("\n", $keys)),
            'key_count' => count($keys),
        ];
    }

    /**
     * Schreibt das Manifest an den festen Storage-Ort (Artefakt-Pfad der Kette).
     *
     * @param  array<string, mixed>  $manifest
     */
    public function store(array $manifest): void {
        // Die local-Disk steht auf 'throw' => false — put() meldet Fehlschläge
        // (Rechte, voller Datenträger) nur per bool. Ohne diese Prüfung meldet
        // ein Freeze „Erfolg" samt DB-Zeile, obwohl keine Baseline liegt.
        $written = Storage::disk('local')->put(
            self::STORAGE_PATH,
            \CommonToolkit\Helper\Data\JsonHelper::encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        if ($written === false) {
            throw new \RuntimeException(sprintf(
                'Integritäts-Baseline konnte nicht geschrieben werden: %s — Dateirechte/Speicherplatz prüfen.',
                Storage::disk('local')->path(self::STORAGE_PATH),
            ));
        }
    }

    /** @return array<string, mixed>|null */
    public function load(): ?array {
        if (! Storage::disk('local')->exists(self::STORAGE_PATH)) {
            return null;
        }
        $decoded = json_decode((string) Storage::disk('local')->get(self::STORAGE_PATH), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Baseline erzeugen + speichern + als `baseline`-Zeile und Audit-Ketten-
     * Eintrag verankern (Provenienz). Gemeinsamer Pfad für `integrity:freeze`
     * (source=local) und `release:manifest` (source=release).
     */
    public function freeze(string $source, ?User $creator = null, bool $requirePersistence = true): IntegrityCheck {
        $manifest = $this->build($source, $creator?->id);
        $this->store($manifest);

        $attributes = [
            'ran_at' => now(),
            'status' => IntegrityCheckStatus::Baseline,
            'baseline_source' => $source,
            'baseline_root' => (string) $manifest['root'],
            'files_checked' => count($manifest['files']),
            'triggered_by' => $creator !== null ? 'ui' : 'cli',
            'created_by' => $creator?->id,
        ];

        try {
            $check = IntegrityCheck::query()->create($attributes);
        } catch (\Throwable $e) {
            // Build-Pipelines ohne DB (release:manifest) dürfen die Baseline
            // trotzdem erzeugen; der lokale Freeze braucht den Audit-Anker.
            if ($requirePersistence) {
                throw $e;
            }
            \Illuminate\Support\Facades\Log::warning('integrity.freeze_unpersisted', ['error' => $e->getMessage()]);

            return new IntegrityCheck($attributes);
        }

        $this->audit('integrity.freeze', $check, $creator, [
            'source' => $source,
            'root' => (string) $manifest['root'],
            'files' => count($manifest['files']),
            'packages' => count($manifest['packages']),
        ]);

        // Externe Verankerung (MVP-447): ohne konfiguriertes Backupziel oder
        // bei Transportfehlern still übersprungen — nie Fail-Ursache.
        app(IntegrityAnchorService::class)->push($manifest, $check->exists ? $check : null);

        return $check;
    }

    /**
     * Vollständiger Prüflauf: Diff gegen die Baseline + Signatur-/Artefakt-
     * kette (release.json, falls vorhanden), Ergebnis persistiert + in der
     * Audit-Kette verankert, Zustandswechsel-Alarm an Plattform-Admins.
     * Einziger Prüfpfad für CLI, Scheduler und Admin-UI.
     */
    public function runVerification(string $trigger = 'cli', bool $withAnchor = false): IntegrityCheck {
        $startedAt = microtime(true);
        $manifest = $this->load();

        if ($manifest === null) {
            return $this->persistRun(IntegrityCheckStatus::MissingBaseline, null, null, $trigger, $startedAt);
        }

        try {
            $comparison = $this->compare($manifest);
            if ($withAnchor) {
                $comparison = $this->withAnchorSignal($comparison, $manifest);
            }
            $status = $comparison->clean() ? IntegrityCheckStatus::Ok : IntegrityCheckStatus::Deviation;

            $check = $this->persistRun($status, $manifest, $comparison, $trigger, $startedAt);
            app(IntegrityLockdownService::class)->evaluate($check, $manifest, $comparison);

            return $check;
        } catch (\Throwable $e) {
            report($e);

            return $this->persistRun(IntegrityCheckStatus::Error, $manifest, null, $trigger, $startedAt, $e->getMessage());
        }
    }

    /**
     * Externes Anker-Signal (MVP-447) in den Vergleich einweben: ein
     * Root-/Historien-Bruch gegen den Anker ist eine echte Abweichung
     * (`chain`), fehlender/nicht lesbarer Anker nur ein Hinweis (`warnings`).
     *
     * @param  array<string, mixed>  $manifest
     */
    private function withAnchorSignal(IntegrityComparison $comparison, array $manifest): IntegrityComparison {
        $lastCheck = IntegrityCheck::query()
            ->whereIn('status', [IntegrityCheckStatus::Ok->value, IntegrityCheckStatus::Deviation->value])
            ->latest('ran_at')
            ->latest('id')
            ->first();

        $result = app(IntegrityAnchorService::class)->compare($manifest, $lastCheck);

        return match ($result['state']) {
            'mismatch' => new IntegrityComparison(
                added: $comparison->added,
                modified: $comparison->modified,
                deleted: $comparison->deleted,
                packages: $comparison->packages,
                chain: [...$comparison->chain, ...$result['issues']],
                env: $comparison->env,
                warnings: $comparison->warnings,
            ),
            'unavailable' => new IntegrityComparison(
                added: $comparison->added,
                modified: $comparison->modified,
                deleted: $comparison->deleted,
                packages: $comparison->packages,
                chain: $comparison->chain,
                env: $comparison->env,
                warnings: [...$comparison->warnings, ...$result['issues']],
            ),
            default => $comparison,
        };
    }

    /**
     * Diff des aktuellen Baums gegen ein Baseline-Manifest; ergänzt Befunde
     * der Signatur-/Artefaktkette (nur wenn ein release.json vorliegt —
     * unsignierte lokale Baselines bleiben reine Drift-Erkennung).
     *
     * @param  array<string, mixed>  $manifest
     */
    public function compare(array $manifest): IntegrityComparison {
        $baselineFiles = $this->indexByKey(is_array($manifest['files'] ?? null) ? $manifest['files'] : [], 'path');
        $currentFiles = $this->indexByKey($this->scanFiles(), 'path');

        $added = array_values(array_diff(array_keys($currentFiles), array_keys($baselineFiles)));
        $deleted = array_values(array_diff(array_keys($baselineFiles), array_keys($currentFiles)));
        $modified = [];
        foreach (array_intersect_key($currentFiles, $baselineFiles) as $path => $entry) {
            if (! hash_equals((string) $baselineFiles[$path]['sha256'], (string) $entry['sha256'])) {
                $modified[] = $path;
            }
        }

        $baselinePackages = $this->indexByKey(is_array($manifest['packages'] ?? null) ? $manifest['packages'] : [], 'name');
        $currentPackages = $this->indexByKey($this->scanPackages(), 'name');
        $packages = [];
        foreach ($currentPackages as $name => $entry) {
            $expected = $baselinePackages[$name]['sha256'] ?? null;
            if ($expected === null) {
                $packages[] = $name . ' (neu)';
            } elseif (! hash_equals((string) $expected, (string) $entry['sha256'])) {
                $packages[] = $name;
            }
        }
        foreach (array_diff_key($baselinePackages, $currentPackages) as $name => $entry) {
            $packages[] = $name . ' (entfernt)';
        }

        return new IntegrityComparison(
            added: $added,
            modified: $modified,
            deleted: $deleted,
            packages: $packages,
            chain: $this->chainIssues(),
            env: $this->envIssues($manifest),
            warnings: $this->gitWarnings($manifest),
        );
    }

    /**
     * `.env`-Drift gegen den Baseline-Fingerabdruck (MVP-447): unterscheidet
     * „Werte geändert" von „Schlüsselsatz geändert", ohne Werte anzufassen.
     * Ohne Baseline-Fingerabdruck (Release-Baseline) kein Signal.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function envIssues(array $manifest): array {
        $baseline = is_array($manifest['env'] ?? null) ? $manifest['env'] : null;
        if ($baseline === null) {
            return [];
        }

        $current = $this->envFingerprint();
        if ($current === null) {
            return [(string) __('integrity.env.missing')];
        }

        if (hash_equals((string) ($baseline['file'] ?? ''), $current['file'])) {
            return [];
        }

        return [
            hash_equals((string) ($baseline['keys'] ?? ''), $current['keys'])
                ? (string) __('integrity.env.values_changed')
                : (string) __('integrity.env.keys_changed', [
                    'before' => (int) ($baseline['key_count'] ?? 0),
                    'after' => $current['key_count'],
                ]),
        ];
    }

    /**
     * Git-Sekundär-Check (MVP-447): HEAD gegen den `build`-Hash der Baseline
     * und ein leerer Arbeitsbaum im Scan-Scope. Reines WARN — nicht jede
     * Installation deployt via Git, und `.git` ist selbst manipulierbar.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function gitWarnings(array $manifest): array {
        if (! is_dir($this->basePath() . DIRECTORY_SEPARATOR . '.git')) {
            return [];
        }

        $warnings = [];
        $head = app(SbomGenerator::class)->resolveGitHash();
        $expected = is_array($manifest['application'] ?? null)
            ? (string) ($manifest['application']['build'] ?? '')
            : '';

        if ($head !== null && $expected !== '' && ! str_starts_with($head, $expected) && ! str_starts_with($expected, $head)) {
            $warnings[] = (string) __('integrity.git.head_mismatch', ['head' => $head, 'expected' => $expected]);
        }

        $dirty = $this->gitDirtyPaths();
        if ($dirty !== []) {
            $warnings[] = (string) __('integrity.git.dirty', [
                'count' => count($dirty),
                'paths' => implode(', ', array_slice($dirty, 0, 5)),
            ]);
        }

        return $warnings;
    }

    /**
     * Geänderte Pfade laut `git status --porcelain`, beschränkt auf den
     * Scan-Scope (`integrity.paths` + `root_files`).
     *
     * @return list<string>
     */
    private function gitDirtyPaths(): array {
        try {
            $output = @shell_exec('git -C ' . escapeshellarg($this->basePath()) . ' status --porcelain 2>/dev/null');
        } catch (\Throwable) {
            return [];
        }
        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        $scope = array_map(static fn($p): string => (string) $p, (array) config('integrity.paths', []));
        $rootFiles = array_map(static fn($p): string => (string) $p, (array) config('integrity.root_files', []));

        $dirty = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $path = trim(mb_substr((string) $line, 3));
            if ($path === '') {
                continue;
            }
            // Renames melden "alt -> neu"; der neue Pfad ist der relevante.
            if (str_contains($path, ' -> ')) {
                $path = trim((string) substr($path, (int) strpos($path, ' -> ') + 4));
            }
            $path = trim($path, '"');

            $inScope = in_array($path, $rootFiles, true);
            foreach ($scope as $prefix) {
                if ($prefix !== '' && str_starts_with($path, rtrim($prefix, '/') . '/')) {
                    $inScope = true;
                    break;
                }
            }
            if ($inScope && ! $this->isExcludedPath($this->basePath() . DIRECTORY_SEPARATOR . $path)) {
                $dirty[] = $path;
            }
        }

        return array_values(array_unique($dirty));
    }

    /**
     * Persistierte/ausgegebene Befundlisten je Kategorie deckeln — die
     * vollständigen Zählwerte bleiben in den *_count-Spalten erhalten.
     *
     * @return array<string, list<string>>
     */
    public function cappedFindings(IntegrityComparison $comparison): array {
        $cap = max(1, (int) config('integrity.max_findings', 50));
        $findings = [];
        foreach ([
            'added' => $comparison->added,
            'modified' => $comparison->modified,
            'deleted' => $comparison->deleted,
            'packages' => $comparison->packages,
            'chain' => $comparison->chain,
            'env' => $comparison->env,
            'warnings' => $comparison->warnings,
        ] as $key => $list) {
            if ($list !== []) {
                $findings[$key] = array_slice($list, 0, $cap);
            }
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    //  Scan
    // ------------------------------------------------------------------

    /**
     * Verzeichnisse des dateiweisen Scan-Scope für den inotify-Wächter
     * (Feature 097, MVP-453): die Scan-Wurzel (für root_files) + alle
     * Unterverzeichnisse der konfigurierten `paths`; Ausschlüsse und Symlinks
     * exakt wie beim Datei-Scan. vendor/ ist BEWUSST nicht enthalten — je-
     * Verzeichnis-Watches über den gesamten vendor-Baum sind untypisch groß;
     * vendor-Drift deckt der periodische Lauf ab (der ausgelöste Verify prüft
     * ohnehin den gesamten Scope inkl. vendor).
     *
     * @return list<string> absolute Verzeichnispfade
     */
    public function watchableDirectories(): array {
        $base = $this->basePath();
        $dirs = [$base];

        foreach ((array) config('integrity.paths', []) as $rel) {
            $absolute = $base . DIRECTORY_SEPARATOR . (string) $rel;
            if (! is_dir($absolute) || is_link($absolute)) {
                continue;
            }
            $dirs[] = $absolute;
            $stack = [$absolute];
            while ($stack !== []) {
                $dir = array_pop($stack);
                foreach (@scandir($dir) ?: [] as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $path = $dir . DIRECTORY_SEPARATOR . $item;
                    if (! is_dir($path) || is_link($path) || $this->isExcludedPath($path)) {
                        continue;
                    }
                    $dirs[] = $path;
                    $stack[] = $path;
                }
            }
        }

        return array_values(array_unique($dirs));
    }

    /** Ob ein absoluter Pfad unter einem konfigurierten Ausschluss-Präfix liegt. */
    public function isExcludedPath(string $absolute): bool {
        $base = $this->basePath();
        $relative = ltrim(substr($absolute, strlen($base)), DIRECTORY_SEPARATOR);
        foreach ((array) config('integrity.exclude', []) as $p) {
            $exclude = str_replace('/', DIRECTORY_SEPARATOR, trim((string) $p, '/'));
            if ($exclude !== '' && ($relative === $exclude || str_starts_with($relative, $exclude . DIRECTORY_SEPARATOR))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Namen (nicht Pfade), auf die der Wächter am Scan-Wurzel-Verzeichnis
     * reagieren soll: die konfigurierten root_files + die Top-Level-Namen der
     * überwachten paths. Filtert am Repo-Root das Rauschen von .env/.git &
     * Co. heraus (die nicht Teil der Baseline sind).
     *
     * @return list<string>
     */
    public function rootWatchNames(): array {
        $names = [];
        foreach ((array) config('integrity.root_files', []) as $f) {
            $names[] = (string) $f;
        }
        foreach ((array) config('integrity.paths', []) as $rel) {
            $names[] = (string) $rel;
        }

        return array_values(array_unique($names));
    }

    /** Scan-Wurzel: base_path(), in Tests via integrity.base umlenkbar. */
    public function basePath(): string {
        $base = (string) config('integrity.base', '');

        return $base !== '' ? rtrim($base, DIRECTORY_SEPARATOR) : base_path();
    }

    /** @return list<array{path: string, sha256: string, bytes: int}> */
    private function scanFiles(): array {
        $base = $this->basePath();
        $entries = [];

        foreach ((array) config('integrity.paths', []) as $dir) {
            $absolute = $base . DIRECTORY_SEPARATOR . $dir;
            if (! is_dir($absolute) || is_link($absolute)) {
                continue;
            }
            foreach ($this->walk($absolute) as $file) {
                $entries[] = $this->fileEntry($base, $file);
            }
        }

        foreach ((array) config('integrity.root_files', []) as $rootFile) {
            $absolute = $base . DIRECTORY_SEPARATOR . $rootFile;
            if (is_file($absolute)) {
                $entries[] = $this->fileEntry($base, $absolute);
            }
        }

        $entries = array_values(array_filter($entries));
        usort($entries, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $entries;
    }

    /** @return list<array{name: string, sha256: string, files: int}> */
    private function scanPackages(): array {
        if (! (bool) config('integrity.vendor.enabled', true)) {
            return [];
        }

        $base = $this->basePath();
        $vendor = $base . DIRECTORY_SEPARATOR . (string) config('integrity.vendor.path', 'vendor');
        if (! is_dir($vendor)) {
            return [];
        }

        $packages = [];

        // vendor-Root (autoload.php) + composer/ + bin/ als Pseudo-Paket.
        $autoloaderFiles = [];
        foreach (glob($vendor . '/*.php') ?: [] as $file) {
            $autoloaderFiles[] = $file;
        }
        foreach (['composer', 'bin'] as $sub) {
            $dir = $vendor . DIRECTORY_SEPARATOR . $sub;
            if (is_dir($dir)) {
                foreach ($this->walk($dir) as $file) {
                    $autoloaderFiles[] = $file;
                }
            }
        }
        if ($autoloaderFiles !== []) {
            $packages[] = $this->packageEntry(self::AUTOLOADER_PACKAGE, $base, $autoloaderFiles);
        }

        foreach ($this->subDirectories($vendor) as $vendorDir) {
            foreach ($this->subDirectories($vendorDir) as $packageDir) {
                $files = iterator_to_array($this->walk($packageDir), false);
                if ($files === []) {
                    continue;
                }
                $name = basename($vendorDir) . '/' . basename($packageDir);
                $packages[] = $this->packageEntry($name, $base, $files);
            }
        }

        usort($packages, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $packages;
    }

    /**
     * Rekursiver Datei-Walk: Symlinks und konfigurierte Ausschluss-Präfixe
     * werden beim Traversieren übersprungen (nicht erst nachgefiltert).
     *
     * @return \Generator<string>
     */
    private function walk(string $directory): \Generator {
        $base = $this->basePath();
        $excludes = array_map(
            static fn(string $p): string => str_replace('/', DIRECTORY_SEPARATOR, trim($p, '/')),
            (array) config('integrity.exclude', []),
        );

        $stack = [$directory];
        while ($stack !== []) {
            $dir = array_pop($stack);
            $items = @scandir($dir);
            if ($items === false) {
                continue;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                $relative = ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR);
                foreach ($excludes as $exclude) {
                    if ($relative === $exclude || str_starts_with($relative, $exclude . DIRECTORY_SEPARATOR)) {
                        continue 2;
                    }
                }
                if (is_link($path)) {
                    if (is_file($path)) {
                        yield $path; // Datei-Symlink (vendor/bin) hasht den Zielinhalt
                    }

                    continue; // Verzeichnis-Symlinks nie betreten (Loop-/Upload-Schutz)
                }
                if (is_dir($path)) {
                    $stack[] = $path;
                } elseif (is_file($path)) {
                    yield $path;
                }
            }
        }
    }

    /** @return list<string> */
    private function subDirectories(string $directory): array {
        $dirs = [];
        foreach (@scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && ! is_link($path)) {
                $dirs[] = $path;
            }
        }
        sort($dirs);

        return $dirs;
    }

    /** @return array{path: string, sha256: string, bytes: int}|null */
    private function fileEntry(string $base, string $absolute): ?array {
        try {
            $hash = File::hash($absolute, HashAlgorithm::SHA256);
        } catch (\Throwable) {
            return null; // Race: Datei zwischen Listing und Hash verschwunden
        }

        return [
            'path' => $this->relativePath($base, $absolute),
            'sha256' => $hash,
            'bytes' => (int) (@filesize($absolute) ?: 0),
        ];
    }

    /**
     * Aggregat je Paket: SHA-256 über die sortierten "relpath\nhash\n"-Zeilen.
     *
     * @param  list<string>  $files
     * @return array{name: string, sha256: string, files: int}
     */
    private function packageEntry(string $name, string $base, array $files): array {
        $lines = [];
        foreach ($files as $file) {
            try {
                $lines[] = $this->relativePath($base, $file) . "\n" . File::hash($file, HashAlgorithm::SHA256) . "\n";
            } catch (\Throwable) {
                // Race beim Listing — Datei überspringen
            }
        }
        sort($lines);

        return [
            'name' => $name,
            'sha256' => CryptoHelper::hash(implode('', $lines)),
            'files' => count($lines),
        ];
    }

    private function relativePath(string $base, string $absolute): string {
        return str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($absolute, strlen($base)), DIRECTORY_SEPARATOR));
    }

    /**
     * @param  list<array{path?: string, name?: string, sha256: string}>  $files
     * @param  list<array{name: string, sha256: string, files: int}>  $packages
     */
    private function rootHash(array $files, array $packages): string {
        return CryptoHelper::hash(ReleaseManifestService::canonicalJson([
            'files' => $files,
            'packages' => $packages,
        ]));
    }

    // ------------------------------------------------------------------
    //  Kette, Persistenz, Alarm
    // ------------------------------------------------------------------

    /**
     * Signatur-/Artefaktkette über den bestehenden {@see ReleaseVerifier}:
     * deckt integrity.json als Artefakt und die Ed25519-Signatur ab.
     *
     * @return list<string>
     */
    private function chainIssues(): array {
        if (! Storage::disk('local')->exists(ReleaseManifestService::STORAGE_PATH)) {
            return [];
        }
        $release = json_decode((string) Storage::disk('local')->get(ReleaseManifestService::STORAGE_PATH), true);
        if (! is_array($release)) {
            return ['release.json ist kein gültiges JSON-Objekt.'];
        }

        $result = app(ReleaseVerifier::class)->verify($release);

        return $result->valid ? [] : $result->issues;
    }

    /** @param  array<string, mixed>|null  $manifest */
    private function persistRun(
        IntegrityCheckStatus $status,
        ?array $manifest,
        ?IntegrityComparison $comparison,
        string $trigger,
        float $startedAt,
        ?string $error = null,
    ): IntegrityCheck {
        $findings = $comparison !== null ? $this->cappedFindings($comparison) : [];
        if ($error !== null) {
            $findings['error'] = [mb_substr($error, 0, 500)];
        }

        $check = IntegrityCheck::query()->create([
            'ran_at' => now(),
            'status' => $status,
            'baseline_source' => is_array($manifest) ? (string) ($manifest['source'] ?? '') : null,
            'baseline_root' => is_array($manifest) ? (string) ($manifest['root'] ?? '') : null,
            'files_checked' => $comparison !== null && is_array($manifest) ? count((array) ($manifest['files'] ?? [])) : 0,
            'added_count' => $comparison !== null ? count($comparison->added) : 0,
            'modified_count' => $comparison !== null ? count($comparison->modified) : 0,
            'deleted_count' => $comparison !== null ? count($comparison->deleted) : 0,
            'packages_changed_count' => $comparison !== null ? count($comparison->packages) : 0,
            'findings' => $findings !== [] ? $findings : null,
            'findings_hash' => $comparison?->findingsHash(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'triggered_by' => $trigger,
        ]);

        $this->audit('integrity.check', $check, null, [
            'status' => $status->value,
            'root' => $check->baseline_root,
            'added' => $check->added_count,
            'modified' => $check->modified_count,
            'deleted' => $check->deleted_count,
            'packages' => $check->packages_changed_count,
        ]);

        $this->notifyOnStateChange($check);
        $this->prune();

        return $check;
    }

    /**
     * Zustandswechsel-Alarm an Plattform-Admins (MVP-441): Alarm nur bei
     * neuem/verändertem Befund-Set, Entwarnung beim Wechsel deviation→ok.
     * Baseline-/Fehler-Zeilen zählen nicht als Alarm-Zustand.
     */
    private function notifyOnStateChange(IntegrityCheck $check): void {
        if (! in_array($check->status, [IntegrityCheckStatus::Ok, IntegrityCheckStatus::Deviation], true)) {
            return;
        }

        $previous = IntegrityCheck::query()
            ->whereKeyNot($check->id)
            ->whereIn('status', [IntegrityCheckStatus::Ok->value, IntegrityCheckStatus::Deviation->value])
            ->latest('ran_at')
            ->latest('id') // ran_at hat Sekundenauflösung — id bricht Gleichstände
            ->first();

        if ($check->status === IntegrityCheckStatus::Deviation) {
            $unchanged = $previous !== null
                && $previous->status === IntegrityCheckStatus::Deviation
                && $previous->findings_hash === $check->findings_hash;
            if ($unchanged) {
                return; // bekannter Befund — kein täglicher Doppel-Alarm
            }
            $this->notifyPlatformAdmins($check, 'notification.message.integrity_deviation_title', 'notification.message.integrity_deviation_message');

            return;
        }

        if ($previous?->status === IntegrityCheckStatus::Deviation) {
            $this->notifyPlatformAdmins($check, 'notification.message.integrity_restored_title', 'notification.message.integrity_restored_message');
        }
    }

    private function notifyPlatformAdmins(IntegrityCheck $check, string $titleKey, string $messageKey): void {
        $admins = User::query()->where('is_platform_admin', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        $params = [
            'added' => $check->added_count,
            'modified' => $check->modified_count,
            'deleted' => $check->deleted_count,
            'packages' => $check->packages_changed_count,
            'source' => (string) $check->baseline_source,
        ];

        Notification::send($admins, new GenericEventNotification(
            NotificationEvent::SecurityIntegrity,
            [
                'title' => (string) __($titleKey, $params),
                'title_key' => $titleKey,
                'title_params' => $params,
                'message' => (string) __($messageKey, $params),
                'message_key' => $messageKey,
                'message_params' => $params,
                'url' => route('admin.integrity.index'),
            ],
            ['database', 'mail'],
        ));
    }

    /** @param  array<string, mixed>  $changes */
    private function audit(string $event, IntegrityCheck $check, ?User $user, array $changes): void {
        try {
            AuditLog::query()->create([
                'organization_id' => null,
                'user_id' => $user?->id,
                'event' => $event,
                'auditable_type' => IntegrityCheck::class,
                'auditable_id' => $check->id,
                'changes' => $changes,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('integrity.audit_failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /** Prüfhistorie schlank halten (Default 24 Monate). */
    private function prune(): void {
        $months = max(1, (int) config('integrity.retention_months', 24));
        IntegrityCheck::query()->where('ran_at', '<', now()->subMonths($months))->delete();
    }

    /**
     * @param  array<int|string, mixed>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function indexByKey(array $entries, string $key): array {
        $indexed = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry[$key], $entry['sha256'])) {
                $indexed[(string) $entry[$key]] = $entry;
            }
        }

        return $indexed;
    }
}
