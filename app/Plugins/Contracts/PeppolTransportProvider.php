<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolTransportProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use ERechnungToolkit\Contracts\AccessPointClientInterface;

/**
 * Anbindung an einen zertifizierten Peppol-Access-Point-Provider
 * (Feature 066, MVP-734).
 *
 * WorkDiary betreibt **keinen** eigenen Access Point: AS4, PKI und
 * Peppol-Zertifizierung bleiben beim Provider. Ein Plugin mit dieser Fähigkeit
 * liefert nur den Client zu dessen Schnittstelle; Umschlag (SBDH),
 * Teilnehmerauflösung (SML/SMP) und BIS-Prüfung kommen aus dem
 * php-erechnung-toolkit.
 *
 * Die Organisation steckt in der Plugin-Konfiguration (`plugin_settings` je
 * Organisation) — deshalb reicht die Organisations-ID als Kontext.
 */
interface PeppolTransportProvider {
    /**
     * Access-Point-Client der Organisation. `null`, wenn für diese
     * Organisation keine (vollständigen) Zugangsdaten hinterlegt sind — der
     * Aufrufer meldet das als Konfigurationsfehler, statt blind zu senden.
     */
    public function peppolAccessPoint(?int $organizationId = null): ?AccessPointClientInterface;

    /**
     * Eigene Peppol-Teilnehmerkennung der Organisation in kanonischer Form
     * (`<ICD>:<Kennung>`), z. B. `9930:DE123456789`. `null` = nicht gepflegt.
     */
    public function peppolSenderId(?int $organizationId = null): ?string;
}
