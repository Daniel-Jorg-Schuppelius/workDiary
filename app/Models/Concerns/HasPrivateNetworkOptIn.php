<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasPrivateNetworkOptIn.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Auditiertes SSRF-Opt-in mit Plattform-Vorbehalt (Sicherheitsscan
 * 2026-08-23, S-65).
 *
 * `allow_private_network` schaltet die UrlSafety-Leitplanke ab — gesetzt vom
 * **Org-Admin**, also von genau der Rolle, gegen deren Fehlgriffe die
 * Leitplanke schützt. Im Einzelplatzbetrieb ist das richtig: dort steht der
 * CardDAV-Server tatsächlich im eigenen Netz. Im SaaS-Betrieb ist es das
 * nicht — dort erlaubt es einem Mandanten, den Server auf interne Adressen
 * zeigen zu lassen.
 *
 * `plugins.allow_private_network_opt_in` (Vorgabe: an) entscheidet das für die
 * Instanz. Der gespeicherte Wert bleibt unberührt: die Oberfläche zeigt
 * weiterhin, was gesetzt wurde — es wirkt nur nicht.
 */
trait HasPrivateNetworkOptIn {
    public function allowsPrivateNetwork(): bool {
        if (! (bool) config('plugins.allow_private_network_opt_in', true)) {
            return false;
        }

        return (bool) $this->getAttribute('allow_private_network');
    }
}
