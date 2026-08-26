<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeNaptrResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use ERechnungToolkit\Contracts\DnsNaptrResolverInterface;

/**
 * DNS-Naht der Peppol-Teilnehmerauflösung im Test (Feature 066, MVP-734).
 *
 * Die SML-Auflösung ist eine echte DNS-Abfrage; in der Testsuite darf keine
 * stattfinden. Der Fake liefert eine feste Antwort und merkt sich die
 * abgefragten Namen, damit Tests prüfen können, dass der Zwischenspeicher
 * greift (kein zweiter Lookup).
 *
 * @see \ERechnungToolkit\Peppol\Dns\SystemNaptrResolver für die echte Auflösung
 */
final class FakeNaptrResolver implements DnsNaptrResolverInterface {
    /** @var list<string> */
    public array $queried = [];

    /** @param list<string> $smpUrls Leere Liste = Teilnehmer nicht in Peppol registriert. */
    public function __construct(private readonly array $smpUrls = []) {}

    /** @return list<string> */
    public function resolveSmpUrls(string $dnsName): array {
        $this->queried[] = $dnsName;

        return $this->smpUrls;
    }
}
