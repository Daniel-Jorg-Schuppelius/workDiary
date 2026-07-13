<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuzzleWebdavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Services;

use App\Models\WebdavConnection;
use App\Plugins\Support\Mirror\RemoteFileGateway;
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use GuzzleHttp\Client;

/**
 * Standard-Factory (Feature 058): baut je Anbindung ein {@see HttpWebdavGateway}
 * mit einem frischen Guzzle-Client. Im Test durch eine Fake-Factory ersetzt.
 */
class GuzzleWebdavGatewayFactory implements WebdavGatewayFactory {
    public function for(WebdavConnection $connection): RemoteFileGateway {
        return new HttpWebdavGateway(new Client(['timeout' => 30]), $connection);
    }
}
