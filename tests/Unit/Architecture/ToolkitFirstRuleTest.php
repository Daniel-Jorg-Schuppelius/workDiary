<?php
/*
 * Created on   : Fri Sep 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolkitFirstRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Toolkit-first" (Toolkit-Audit 2026-09): In `app/` stehen
 * für diese Aufgaben keine PHP-Bordmittel oder Eigenbauten, sondern die eigenen
 * Toolkits. Nach den Konsolidierungen im Sommer war der Eigenbau wieder
 * eingerissen (JSON-Encode von 25 auf 75 rohe Aufrufe, ≈370 rohe
 * Dateisystem-Aufrufe) — und hatte echte Fehler gebracht: Lohn-CSV ohne
 * Quoting, symlink-folgendes rrmdir, Menge „10" als „1", abgeschnittene
 * Steuerbeträge. Begründete Ausnahmen stehen je Regel mit Grund in der Allowlist.
 *
 * Referenz der Toolkit-API: WorkDiary-Architecture/toolkit-capability-map.md.
 */
class ToolkitFirstRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Regel => [Muster, Toolkit-Ersatz, Allowlist (Pfad-Präfix => Begründung)].
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    private const RULES = [
        'json-encode' => [
            '/(?<![\w>:$])(?<!\w\\\\)(?<!function\s)json_encode\s*\(/',
            'CommonToolkit\Helper\Data\JsonHelper::encode($data, $flags) — wirft bei Fehlern statt still "" zu liefern',
            [],
        ],
        'number-format' => [
            '/(?<![\w>:$])(?<!\w\\\\)(?<!function\s)number_format\s*\(/',
            'NumberHelper::toGermanFormat/toUSFormat (float!), Money::format, Decimal::format',
            [],
        ],
        'filesystem' => [
            '/(?<![\w>:$])(?<!\w\\\\)(?<!function\s)(?:file_get_contents|file_put_contents|fopen|fwrite|fread|unlink|mkdir|rmdir|scandir|glob|tempnam|touch|chmod|filesize|filemtime|fileperms|is_file|is_dir|is_readable|is_writable|file_exists|realpath|copy|rename|sys_get_temp_dir)\s*\(/',
            'CommonToolkit\Helper\FileSystem\{File, Folder, Files} (read/write mit Rechten, withTemp, isFile/isDirectory ohne Log, openStream/readChunks, get(…, skip:), resolveWithin, delete …)',
            [
                'app/Services/Backup/Support/SecretStreamFile.php' => 'sodium-secretstream: längenpräfixierte 1-MiB-Blöcke auf offenen Handles (File::openStream), readChunks kennt kein Blockformat',
                'app/Services/Backup/BackupSnapshotBuilder.php' => 'splitParts teilt GB-Archive an Teilgrenzen mitten im Block — fread/fwrite auf Handles aus File::openStream',
                'app/Support/CsvExport.php' => 'streamt nach php://output (StreamedResponse), kein Dateipfad',
                'app/Support/DatabaseHealth.php' => 'Fast-Path bei DB-Ausfall, je Request: race-tolerante Best-effort-Marker zwischen Workern (@unlink/@filemtime), ohne Log-Rauschen',
                'app/Services/Print/Preflight/BasicPreflightProvider.php' => 'liest 8 Byte aus einem Storage-Disk-Stream (kann S3 sein), kein lokaler Pfad',
            ],
        ],
        'directory-iterator' => [
            '/\b(?:RecursiveDirectoryIterator|DirectoryIterator|FilesystemIterator)\b/',
            'Files::get($dir, $recursive, …) / Folder::get / Folder::size',
            [],
        ],
        'laravel-file-facade' => [
            '/Illuminate\\\\Support\\\\Facades\\\\File\b|Illuminate\\\\Filesystem\\\\Filesystem\b/',
            'File/Folder/Files des common-toolkits (Folder::delete ist ab v1.36 symlink-sicher)',
            [],
        ],
        'zip-archive' => [
            '/\bZipArchive\b/',
            'FileTypes\ZipFile (createFromStrings/createFromEntries/readEntries(FromFile)/extract — mit Limits und Zip-Slip-Schutz)',
            [],
        ],
        'process' => [
            '/Symfony\\\\Component\\\\Process\\\\Process\b|(?<![\w>:$])(?<!\w\\\\)(?<!function\s)(?:proc_open|shell_exec|passthru|exec)\s*\(/',
            'CommonToolkit\Helper\Shell::run($argv, $timeout, $env, …) oder ConfigToolkit\CommandBuilder',
            [],
        ],
        'mime-detection' => [
            '/new\s+\\\\?finfo\b|(?<![\w>:$])(?<!\w\\\\)(?<!function\s)(?:finfo_open|finfo_buffer|finfo_file|mime_content_type)\s*\(/',
            'File::mimeType($path) / File::mimeTypeFromContent($bytes) / File::extensionForMimeType / mimeTypeForExtension',
            [],
        ],
        'wordwrap' => [
            '/(?<![\w>:$])(?<!\w\\\\)(?<!function\s)wordwrap\s*\(/',
            'StringHelper::wrap — wordwrap zählt Bytes, nicht Zeichen',
            [],
        ],
        'trailing-zeros' => [
            '/rtrim\s*\(\s*rtrim\s*\(/',
            'NumberHelper::trimTrailingZeros bzw. toGermanFormat/toUSFormat(…, trimTrailingZeros: true) — rtrim machte aus „10" eine „1"',
            [],
        ],
        'email-validation' => [
            '/FILTER_VALIDATE_EMAIL/',
            'EmailHelper::isEmail / EmailHelper::normalize',
            [],
        ],
        'whitespace' => [
            '/preg_replace\s*\(\s*[\'"]\/\\\\s\+\/u?[\'"]\s*,\s*[\'"] ?[\'"]/',
            'StringHelper::normalizeWhitespace / collapseWhitespace / removeWhitespace (…, unicode: true)',
            [],
        ],
        'data-url' => [
            '/[\'"]data:[\'"]\s*\.|[\'"]data:[^\'"]*;base64,[\'"]\s*\./',
            'DataUrlHelper::encode($bytes, $mime) / DataUrlHelper::decode',
            [],
        ],
        'base64url' => [
            '/strtr\s*\(\s*base64_encode|base64_decode\s*\(\s*strtr/',
            'CryptoHelper::base64UrlEncode / base64UrlDecode',
            [],
        ],
    ];

    public function test_app_uses_the_toolkits_instead_of_raw_php(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            $source = $this->withoutComments((string) file_get_contents($file));

            foreach (self::RULES as $rule => [$pattern, , $allowList]) {
                if ($this->isAllowListed($relative, $allowList)) {
                    continue;
                }
                if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) < 1) {
                    continue;
                }
                foreach ($matches[0] as [$snippet, $offset]) {
                    $violations[] = sprintf('[%s] %s:%d — %s', $rule, $relative, $this->lineOf($source, (int) $offset), trim($snippet));
                }
            }
        }

        sort($violations);
        $hints = implode("\n", array_map(
            static fn (string $rule, array $definition): string => sprintf('  %-20s → %s', $rule, $definition[1]),
            array_keys(self::RULES),
            self::RULES,
        ));

        $this->assertSame([], $violations, "Toolkit-first: Diese Stellen bauen nach, was die eigenen Toolkits können.\n"
            . "Ersatz je Regel:\n{$hints}\n"
            . "Begründete Ausnahmen (Geschäftsregel, belegter Verhaltensunterschied) in die Allowlist der Regel.\n\n"
            . implode("\n", $violations));
    }

    /** Ein kaputtes Muster machte das Gate stumm grün — daher Stichproben je Regel. */
    public function test_rules_detect_raw_calls_but_not_methods(): void {
        $hits = [
            'json-encode' => ['$a = json_encode($x);', 'return \\json_encode($x);', '@json_encode($x)'],
            'number-format' => ['number_format($v, 2)'],
            'filesystem' => ['file_put_contents($p, $c)', 'if (is_dir($d))', '@unlink($f);'],
            'zip-archive' => ['new \\ZipArchive()'],
            'process' => ['proc_open($cmd, $spec, $pipes)', 'use Symfony\\Component\\Process\\Process;'],
            'trailing-zeros' => ["rtrim(rtrim(\$q, '0'), '.')"],
            'whitespace' => ["preg_replace('/\\s+/u', ' ', \$t)", "preg_replace('/\\s+/', '', \$id)"],
            'base64url' => ["strtr(base64_encode(\$b), '+/', '-_')"],
        ];
        $misses = [
            'json-encode' => ['JsonHelper::encode($x)', '$this->json_encode($x)', 'function json_encode(', 'Foo\\json_encode($x)'],
            'filesystem' => ['File::isFile($p)', '$disk->copy($a, $b)', 'Str::is_file(', 'function unlink('],
            'whitespace' => ["preg_replace('/\\s+\\d\$/', '', \$name)"],
        ];

        foreach ($hits as $rule => $samples) {
            foreach ($samples as $sample) {
                $this->assertSame(1, preg_match(self::RULES[$rule][0], $sample), "[$rule] erkennt nicht: $sample");
            }
        }
        foreach ($misses as $rule => $samples) {
            foreach ($samples as $sample) {
                $this->assertSame(0, preg_match(self::RULES[$rule][0], $sample), "[$rule] meldet fälschlich: $sample");
            }
        }
    }

    /** Eine Ausnahme ohne Begründung ist ein Vergessen mit Alibi. */
    public function test_allow_list_entries_exist_and_are_justified(): void {
        $problems = [];
        foreach (self::RULES as $rule => [, , $allowList]) {
            foreach ($allowList as $prefix => $reason) {
                if (! file_exists($this->repoRoot() . '/' . rtrim($prefix, '/'))) {
                    $problems[] = "[$rule] $prefix existiert nicht mehr — entfernen";
                }
                if (mb_strlen(trim($reason)) < 40) {
                    $problems[] = "[$rule] $prefix ist nicht begründet";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /** Kommentare entfernen, Zeilenumbrüche behalten (Zeilennummern bleiben stimmig). */
    private function withoutComments(string $source): string {
        $source = (string) preg_replace_callback('~/\*.*?\*/~s', static fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")), $source);

        return (string) preg_replace('~^\s*(//|#(?!\[)).*$~m', '', $source);
    }
}
