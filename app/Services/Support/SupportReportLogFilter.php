<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportLogFilter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Support;

/**
 * Redaktiert sensible Inhalte aus Log-Zeilen vor Aufnahme in einen
 * Supportbericht (MVP-045 §3). Stateful: ID-Surrogate bleiben innerhalb
 * eines Filter-Laufes konsistent (z. B. `user_123` → `user_1` in jeder
 * weiteren Zeile derselben Instanz).
 */
class SupportReportLogFilter {
    /** @var array<string, int> */
    private array $surrogates = [];

    /** @var array<string, int> */
    private array $surrogateCounters = [];

    public function filter(string $line): string {
        // Reihenfolge: erst der spezifische JWT-Marker, dann die allgemeine
        // Zugangsdaten-Regel — sonst verlöre ein Bearer-JWT seine genauere
        // Kennzeichnung.
        $line = $this->redactJwt($line);
        $line = $this->redactCredentials($line);
        $line = $this->redactIban($line);
        $line = $this->redactEmail($line);
        $line = $this->redactIpv4($line);
        $line = $this->redactIpv6($line);
        $line = $this->redactPhone($line);
        $line = $this->surrogateEntityIds($line);

        return $line;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function filterMany(array $lines): array {
        $out = [];
        foreach ($lines as $line) {
            $out[] = $this->filter($line);
        }

        return $out;
    }

    /**
     * Zugangsdaten in Parametern und Kopfzeilen.
     *
     * Der Filter schwärzte JWTs, IBANs, Mail-Adressen, IPs und Rufnummern —
     * aber weder `Authorization: Bearer …` noch `api_key=…`, `token=…`,
     * `password=…` oder `secret=…` (Sicherheitsscan 2026-08-23, S-19).
     * Genau die stehen aber in Integrations-Fehlermeldungen, die im
     * Problem-Melde-Dialog landen.
     */
    private function redactCredentials(string $line): string {
        // Authorization-Header (Bearer/Basic/Token).
        $line = (string) preg_replace(
            '/\b(Bearer|Basic|Token)\s+[A-Za-z0-9._~+\/=-]{8,}/i',
            '$1 <redacted:credential>',
            $line
        );

        // Schlüssel=Wert in Query-Strings, JSON und Array-Ausgaben.
        return (string) preg_replace(
            '/("?\b(?:api[_-]?key|apikey|access[_-]?token|refresh[_-]?token|token|secret|client[_-]?secret|password|passwd|pwd)"?\s*[:=]>?\s*"?)([^"\s,&)}\]]{4,})/i',
            '$1<redacted:credential>',
            $line
        );
    }

    private function redactJwt(string $line): string {
        // JWT: drei base64url-Segmente, durch . getrennt, jeweils 8+ Zeichen.
        return (string) preg_replace(
            '/\b(?:[A-Za-z0-9_-]{8,})\.(?:[A-Za-z0-9_-]{8,})\.(?:[A-Za-z0-9_-]{8,})\b/',
            '<redacted:jwt>',
            $line
        );
    }

    private function redactIban(string $line): string {
        // IBAN: ISO 13616, 15–34 Zeichen, beginnt mit 2 Buchstaben + 2 Ziffern.
        return (string) preg_replace(
            '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/',
            '<redacted:iban>',
            $line
        );
    }

    private function redactEmail(string $line): string {
        return (string) preg_replace(
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            '<redacted:email>',
            $line
        );
    }

    private function redactIpv4(string $line): string {
        return (string) preg_replace(
            '/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\b/',
            '<redacted:ipv4>',
            $line
        );
    }

    private function redactIpv6(string $line): string {
        // IPv6: nur greifen, wenn mindestens ein Hex-Buchstabe a–f vorkommt,
        // sonst kollidiert die Regel mit Uhrzeit-Strings wie "09:00:00".
        return preg_replace_callback(
            '/\b(?:[a-fA-F0-9]{1,4}:){2,7}[a-fA-F0-9]{1,4}\b/',
            static function (array $m): string {
                return preg_match('/[a-fA-F]/', $m[0]) === 1 ? '<redacted:ipv6>' : $m[0];
            },
            $line
        ) ?? $line;
    }

    private function redactPhone(string $line): string {
        // Telefonnummern: ausschließlich Sequenzen mit "+" Präfix.
        // Reine Ziffernblöcke und Datums-/Zeit-Strings ohne "+" werden nicht
        // redagiert — sonst kollidiert der Filter mit Log-Timestamps und IDs.
        return preg_replace_callback(
            '/(?<![A-Za-z0-9])\+\d[\d \-\/]{6,18}\d(?![A-Za-z0-9])/',
            static function (array $m): string {
                $digits = preg_replace('/\D+/', '', $m[0]);
                return strlen($digits ?? '') >= 7 ? '<redacted:phone>' : $m[0];
            },
            $line
        ) ?? $line;
    }

    private function surrogateEntityIds(string $line): string {
        return preg_replace_callback(
            '/\b(user|customer|project|organization|diary|protocol|asset)_(\d+)\b/i',
            function (array $m): string {
                $kind = strtolower($m[1]);
                $original = $m[2];
                $key = $kind . ':' . $original;
                if (! isset($this->surrogates[$key])) {
                    $this->surrogateCounters[$kind] = ($this->surrogateCounters[$kind] ?? 0) + 1;
                    $this->surrogates[$key] = $this->surrogateCounters[$kind];
                }

                return $kind . '_' . $this->surrogates[$key];
            },
            $line
        ) ?? $line;
    }
}
