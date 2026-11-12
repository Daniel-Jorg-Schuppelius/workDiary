<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasuresEffect.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture\Concerns;

/**
 * Gemeinsame Bausteine der Wirkungs-Gates aus dem Ganzbestands-Scan vom
 * 2026-11-10 (MVP-1620): Sie messen nicht, ob etwas da ist, sondern ob es
 * einen Aufrufer, Sender, Setzer oder Leser hat. Erwartet `ScansSourceTree`
 * in der nutzenden Klasse.
 *
 * Baselines liegen als `Schlüssel => Grund` unter `baselines/` und dürfen nur
 * schrumpfen; jedes Gate meldet zusätzlich Einträge, die erfüllt sind.
 */
trait MeasuresEffect {
    /**
     * Schreibende Routen, die kein Browser der App aufruft, sondern ein
     * Fremdsystem — Name als Regex => Grund. Gelten für die Gates „Route
     * ohne Einstieg“ und „Feld ohne Sender“.
     *
     * @var array<string, string>
     */
    private const array MACHINE_ROUTES = [
        '/^api\./' => 'REST-API, Aufrufer sind Fremdsysteme',
        '/webhook|callback/' => 'Webhooks und OAuth-Rückrufe der Anbieter',
        '/^scim\./' => 'SCIM-Provisionierung durch den Identity-Provider',
        '/^mcp(\.|$)/' => 'MCP-Server und dessen OAuth-Registrierung für KI-Clients',
        '/^learning\.cmi5\.lrs\./' => 'LRS-Endpunkte, aufgerufen von cmi5-Inhalten',
        '/^learning\.lti\.(tool\.login|tool\.launch|platform\.auth|deep-linking\.return)$/' => 'LTI-1.3-Anmeldung und Rückkehr, aufgerufen vom Lernsystem',
        '/^(oci-carts|b2b-punchout)\./' => 'Punchout-Übergaben der Einkaufssysteme',
        '/^sso\.saml\.acs$/' => 'SAML-Assertion-Consumer, Post des Identity-Providers',
        '/^admin\.backup\.heartbeat$/' => 'Heartbeat des Backup-Skripts mit Bearer-Token',
    ];

    /** Grund, falls die Route ein Maschinen-Endpunkt ist; sonst null. */
    protected function machineRouteReason(string $name, string $uri): ?string {
        if (str_starts_with($uri, 'api/')) {
            return self::MACHINE_ROUTES['/^api\./'];
        }
        foreach (self::MACHINE_ROUTES as $pattern => $reason) {
            if (preg_match($pattern, $name) === 1) {
                return $reason;
            }
        }

        return null;
    }

    /** Demo-Blöcke und Demo-Seeder zählen nicht als Aufrufer, Setzer oder Leser. */
    protected function isDemoPath(string $relative): bool {
        return str_contains($relative, '/Demo/') || preg_match('~^database/seeders/\w*Demo~', $relative) === 1;
    }

    /**
     * Quelltexte ohne Kommentare, repo-relativer Pfad => Inhalt.
     *
     * @param  list<string>  $directories
     * @return array<string, string>
     */
    protected function codeCorpus(array $directories, string $extensionPattern = '/\.php$/', bool $withDemo = false): array {
        $out = [];
        foreach ($directories as $directory) {
            foreach ($this->filesUnder($directory, $extensionPattern) as $file) {
                $relative = $this->relativePath($file);
                if (! $withDemo && $this->isDemoPath($relative)) {
                    continue;
                }
                $out[$relative] = $this->stripComments((string) file_get_contents($file));
            }
        }

        return $out;
    }

    /** Ausschnitt ab der öffnenden Klammer bei `$open` bis zur passenden schließenden (Strings beachtet). */
    protected function balancedFrom(string $source, int $open): string {
        $depth = 0;
        $quote = null;
        $length = strlen($source);
        for ($i = $open; $i < $length; $i++) {
            $char = $source[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '[' || $char === '(' || $char === '{') {
                $depth++;
            } elseif (($char === ']' || $char === ')' || $char === '}') && --$depth === 0) {
                return substr($source, $open, $i - $open + 1);
            }
        }

        return substr($source, $open);
    }

    /**
     * Prüft eine Baseline `Schlüssel => Grund`: jeder Grund konkret, kein Pauschalgrund.
     *
     * @param  array<string, string>  $baseline
     * @return list<string>
     */
    protected function baselineEntriesWithoutReason(array $baseline): array {
        $weak = [];
        foreach ($baseline as $key => $reason) {
            if (mb_strlen(trim($reason)) < 15 || preg_match('/^(bestand|todo|später|offen)\b/i', trim($reason)) === 1) {
                $weak[] = $key;
            }
        }

        return $weak;
    }
}
