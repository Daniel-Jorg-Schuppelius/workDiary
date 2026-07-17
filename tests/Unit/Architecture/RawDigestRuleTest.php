<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawDigestRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architektur-Gate gegen rohe Digest-Aufrufe (konsolidierungs-audit-2026-07,
 * Befund C1): Nach dem Digest-Sweep 2026-07-02 waren ~40 rohe
 * `hash('sha256', …)`- und 3 `sha1(…)`-Aufrufe wieder eingerissen.
 *
 * Toolkit-Standard ist `CommonToolkit\Helper\Data\CryptoHelper::hash()`
 * (byte-identischer Delegat an PHP-hash(), Default SHA-256; für SHA-1
 * `CryptoHelper::hash($x, HashAlgorithm::SHA1)`, für Roh-Bytes dritter
 * Parameter `true`). `hash_hmac`/`hash_equals` sind bewusst NICHT erfasst.
 *
 * Bewusste Ausnahmen (z. B. Legacy-Passwort-Pfade) gehören mit Begründung
 * in die WHITELIST (Datei- oder Verzeichnis-Präfix relativ zum Repo-Root).
 */
class RawDigestRuleTest extends TestCase {
    /**
     * Bewusst belassene rohe Digest-Aufrufe: Pfad-Präfix → Begründung.
     *
     * @var array<string, string>
     */
    private const WHITELIST = [
        // Byte-genaue Kompatibilität zum Altsystem (Legacy-Klartext-/Hash-
        // Passwortpfade); wird mit dem Legacy-Modul abgelöst, nicht migriert.
        'app/Legacy/' => 'Legacy-Passwort-Pfade: Altsystem-Kompatibilität, bewusst nicht auf CryptoHelper migriert (C1).',
    ];

    /** Verbotene feste Digest-Aufrufe (Substring, Wortgrenze davor). */
    private const BANNED_SUBSTRINGS = [
        "hash('sha256'",
        'hash("sha256"',
        "hash('sha1'",
        'hash("sha1"',
    ];

    public function test_no_raw_digest_calls_in_app(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $violations = [];

        foreach ($this->phpFiles($root . DIRECTORY_SEPARATOR . 'app') as $file) {
            $relative = str_replace([$root . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $file);

            if ($this->isWhitelisted($relative)) {
                continue;
            }

            $source = $this->stripCommentLines((string) file_get_contents($file));

            foreach ($this->digestFindings($source) as [$line, $snippet]) {
                $violations[] = "$relative:$line — $snippet";
            }
        }

        sort($violations);

        $this->assertSame([], $violations, sprintf(
            "Roher Digest-Aufruf gefunden (Digest-Sweep C1, Toolkit-first).\n"
                . "Stattdessen CommonToolkit\\Helper\\Data\\CryptoHelper::hash(\$data) nutzen\n"
                . "(SHA-1: CryptoHelper::hash(\$data, HashAlgorithm::SHA1); Roh-Bytes: dritter Parameter true)\n"
                . "oder die Stelle mit fachlicher Begründung in die WHITELIST eintragen:\n%s",
            implode("\n", $violations),
        ));
    }

    /**
     * @return list<array{int, string}>
     */
    private function digestFindings(string $source): array {
        $findings = [];

        // hash('sha256'|'sha1', …) — nur die globale Funktion; hash_hmac/
        // hash_equals matchen den Substring nicht, ->hash(/::hash( wird
        // über die Wortgrenze davor ausgeschlossen.
        foreach (self::BANNED_SUBSTRINGS as $needle) {
            $offset = 0;
            while (($pos = strpos($source, $needle, $offset)) !== false) {
                $offset = $pos + strlen($needle);
                $before = $pos > 0 ? $source[$pos - 1] : ' ';
                if (preg_match('/[A-Za-z0-9_$>:\\\\]/', $before) === 1) {
                    continue;
                }
                $findings[] = [$this->lineOf($source, $pos), $needle . '…)'];
            }
        }

        // Roher sha1(…)-Aufruf: Wortgrenze davor schließt Methoden (->sha1(),
        // ::sha1()), Variablen ($sha1() ) und zusammengesetzte Namen aus.
        if (preg_match_all('/(?<![A-Za-z0-9_$>:\\\\])sha1\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$match, $pos]) {
                $findings[] = [$this->lineOf($source, (int) $pos), trim($match) . '…)'];
            }
        }

        return $findings;
    }

    private function isWhitelisted(string $relative): bool {
        foreach (array_keys(self::WHITELIST) as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Entfernt reine Kommentarzeilen (//, *, /*), erhält Zeilennummern. */
    private function stripCommentLines(string $source): string {
        $lines = explode("\n", $source);

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                $lines[$i] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function lineOf(string $source, int $offset): int {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $dir): iterable {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
