<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneConnectionGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane;

use App\Support\UrlSafety;

/**
 * Verbindungs-Guard des InvoicePlane-Plugins (Feature 086, MVP-419).
 *
 * Setzt die Betriebsvorgabe „keine frei eingebbare Internet-Datenbank ohne
 * Host-Allowlist und SSRF-/DNS-Rebinding-Schutz" durch:
 *
 * - **Öffentlich routbare** Hosts sind NUR erlaubt, wenn sie in der
 *   Host-Allowlist stehen — und dann nur mit TLS (falls verlangt).
 * - **Private/nicht-routbare** Hosts (privates Netz, VPN, lokaler Connector)
 *   sind ohne Allowlist zulässig; TLS bleibt empfohlen, aber nicht erzwungen.
 *
 * Die eigentliche DNS-Auflösung/Public-IP-Prüfung (Rebinding-Schutz) liefert
 * {@see UrlSafety::isPubliclyRoutableHost()}.
 */
class InvoicePlaneConnectionGuard {
    public function __construct(
        /** @var list<string> */
        private readonly array $hostAllowlist,
        private readonly bool $requireTls,
    ) {}

    public static function fromConfig(): self {
        $allowlist = (array) config('invoiceplane.host_allowlist', []);

        return new self(
            array_values(array_map(static fn ($h): string => mb_strtolower(trim((string) $h)), $allowlist)),
            (bool) config('invoiceplane.require_tls', true),
        );
    }

    /**
     * @throws InvoicePlaneConnectionException
     */
    public function assertAcceptable(string $host, bool $tlsEnabled): void {
        $host = mb_strtolower(trim($host));
        if ($host === '') {
            throw new InvoicePlaneConnectionException('Kein InvoicePlane-Datenbankhost angegeben.');
        }

        if (! UrlSafety::isPubliclyRoutableHost($host)) {
            // Privater Host / VPN / lokaler Connector — zulässig ohne Allowlist.
            return;
        }

        if (! $this->inAllowlist($host)) {
            throw new InvoicePlaneConnectionException(
                'Der InvoicePlane-Host ist öffentlich erreichbar und steht nicht in der Host-Allowlist.',
            );
        }
        if ($this->requireTls && ! $tlsEnabled) {
            throw new InvoicePlaneConnectionException(
                'Für einen öffentlich erreichbaren InvoicePlane-Host ist eine TLS-Verbindung erforderlich.',
            );
        }
    }

    public function isAcceptable(string $host, bool $tlsEnabled): bool {
        try {
            $this->assertAcceptable($host, $tlsEnabled);

            return true;
        } catch (InvoicePlaneConnectionException) {
            return false;
        }
    }

    private function inAllowlist(string $host): bool {
        return in_array($host, $this->hostAllowlist, true);
    }
}
