<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudTransportFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Contracts;

use App\Plugins\Nextcloud\Api\NextcloudWebdavClient;
use SensitiveParameter;

/**
 * Baut je Anbindung (Server-URL + Nutzer + App-Passwort) einen
 * {@see NextcloudWebdavClient}. Über den Container gebunden — Tests ersetzen
 * die Factory durch eine Variante mit gemocktem Guzzle-Client (kein HTTP).
 */
interface NextcloudTransportFactory {
    public function forCredentials(string $serverUrl, string $username, #[SensitiveParameter] string $appPassword): NextcloudWebdavClient;
}
