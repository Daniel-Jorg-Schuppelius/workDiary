<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardedEbicsHttpClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics;

use App\Plugins\Support\PluginHttpFactory;
use App\Support\UrlSafety;
use EbicsApi\Ebics\Contracts\HttpClientInterface;
use EbicsApi\Ebics\Exceptions\TimeoutEbicsException;
use EbicsApi\Ebics\Models\Http\{Request, Response};
use RuntimeException;
use Throwable;

/**
 * HTTP-Schicht der EBICS-Bibliothek über das eigene Fundament
 * (Sicherheitsaudit 2026-10-04, sf-1). Der mitgelieferte cURL-Client verband
 * am zentralen Abrufschutz vorbei und las die Antwort mit Entitätsauflösung;
 * die Bank-Adresse setzt aber ein Mandant.
 *
 * Hier gilt: Ziel beim Verbinden geprüft und an die geprüfte Adresse gebunden,
 * keine Weiterleitungen, Antwort ohne Entitäten und ohne Netzzugriff gelesen.
 */
final class GuardedEbicsHttpClient implements HttpClientInterface {
    private const TIMEOUT_SECONDS = 30.0;

    public function post(string $url, Request $request): Response {
        $pin = UrlSafety::isPubliclyRoutableHttpUrl($url) ? UrlSafety::pinnedResolution($url) : null;
        if ($pin === null) {
            throw new RuntimeException('EBICS-Adresse ist kein öffentlich erreichbares Ziel.');
        }

        try {
            $client = app(PluginHttpFactory::class)->coreClient('ebics', $url);
            $client->setFollowRedirects(false);
            $client->setTimeout(self::TIMEOUT_SECONDS);
            // Kein HTTP-Retry: eine wiederholte Übermittlung wäre eine zweite Einreichung.
            $client->setMaxRetries(1);
            $response = $client->requestResponse('POST', $url, [
                'headers' => ['Content-Type' => 'text/xml; charset=UTF-8'],
                'body' => $request->getContent(),
                ...($pin !== [] ? ['curl' => [CURLOPT_RESOLVE => $pin]] : []),
            ]);
        } catch (Throwable $e) {
            throw new TimeoutEbicsException('EBICS-Anfrage ohne Antwort: ' . class_basename($e));
        }

        if (! $response->successful()) {
            throw new TimeoutEbicsException('EBICS-Anfrage mit HTTP ' . $response->status() . ' beantwortet.');
        }

        return $this->parse((string) $response->body());
    }

    /** Antwort lesen — ohne `LIBXML_NOENT`, damit eine DOCTYPE-Entität nie aufgelöst wird. */
    public function parse(string $contents): Response {
        if ($contents === '') {
            throw new RuntimeException('Response is empty.');
        }

        $document = new Response;
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $document->loadXML($contents, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            throw new RuntimeException('Failed to load XML response.');
        }

        return $document;
    }
}
