<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmpHttpClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Peppol;

use App\Plugins\Support\PluginHttpFactory;
use ERechnungToolkit\Contracts\SmpHttpClientInterface;
use ERechnungToolkit\Peppol\Http\SmpResponse;
use RuntimeException;
use Throwable;

/**
 * Transport-Naht der SMP-Abrufe (Feature 066, MVP-734).
 *
 * Der SMP ist öffentliche Peppol-Infrastruktur, kein Provider-Endpunkt —
 * deshalb der neutrale Core-Client des api-toolkit-Fundaments statt eines
 * Plugin-Clients. Der Umweg über {@see PluginHttpFactory} ist Absicht: er ist
 * der Austauschpunkt, an dem Tests den Guzzle-Transport ersetzen, sodass hier
 * kein Netzzugriff entstehen kann.
 *
 * HTTP-Fehlerstatus sind Antworten (der Toolkit-Vertrag wertet 404 als „nicht
 * registriert"), nur Transportfehler werfen.
 */
final class SmpHttpClient implements SmpHttpClientInterface {
    public const SERVICE_ID = 'peppol-smp';

    public function get(string $url): SmpResponse {
        try {
            $response = app(PluginHttpFactory::class)
                ->coreClient(self::SERVICE_ID, $url)
                ->getResponse($url, [], ['headers' => ['Accept' => 'application/xml']]);
        } catch (Throwable $e) {
            throw new RuntimeException('SMP nicht erreichbar: ' . $e->getMessage(), 0, $e);
        }

        return new SmpResponse($response->status(), $response->body());
    }
}
