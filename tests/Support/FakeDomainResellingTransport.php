<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeDomainResellingTransport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use Closure;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Psr\Http\Message\RequestInterface;

/**
 * Kanonischer Fake-Transport für DomainReselling-Tests (Feature 083). Alle
 * Befehle laufen über dieselbe `call.cgi`-URL, deshalb wird die Antwort anhand
 * des `command`-Parameters im POST-Body ausgewählt. Antworten tragen den
 * abschließenden `EOF`-Marker (sofern nicht bewusst weggelassen).
 */
class FakeDomainResellingTransport {
    /**
     * @param  array<string, string|Closure(array<string,string>):string>  $byCommand  command (klein) → Body/Closure
     */
    public static function fake(array $byCommand, string $default = "code=200\ndescription=ok\nEOF\n"): FakePluginHttp {
        $lower = [];
        foreach ($byCommand as $key => $value) {
            $lower[strtolower($key)] = $value;
        }

        return FakePluginHttp::fake([
            '*call.cgi' => function (RequestInterface $request) use ($lower, $default): Psr7Response {
                parse_str((string) $request->getBody(), $params);
                /** @var array<string, string> $params */
                $command = strtolower((string) ($params['command'] ?? ''));
                $body = $lower[$command] ?? $default;
                if ($body instanceof Closure) {
                    $body = $body($params);
                }

                return FakePluginHttp::response($body);
            },
        ]);
    }

    /**
     * Baut eine QueryDomainList-/StatusDomain-artige Antwort aus Zeilen.
     *
     * @param  list<array<string, string>>  $rows  je Zeile Feld => Wert
     */
    public static function properties(array $rows, int $code = 200): string {
        $lines = ["code={$code}", 'description=Command completed successfully'];
        foreach ($rows as $index => $row) {
            foreach ($row as $name => $value) {
                $lines[] = sprintf('property[%s][%d]=%s', $name, $index, $value);
            }
        }
        $lines[] = 'EOF';

        return implode("\n", $lines) . "\n";
    }
}
