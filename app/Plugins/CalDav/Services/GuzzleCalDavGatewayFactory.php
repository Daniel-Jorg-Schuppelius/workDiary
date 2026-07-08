<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuzzleCalDavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\CalDavConnection;
use App\Plugins\CalDav\Contracts\{CalDavGateway, CalDavGatewayFactory};
use GuzzleHttp\Client;

/**
 * Standard-Factory (Feature 058): baut je Anbindung ein {@see HttpCalDavGateway}
 * mit einem frischen Guzzle-Client. Im Test durch eine Fake-Factory ersetzt.
 */
class GuzzleCalDavGatewayFactory implements CalDavGatewayFactory {
    public function for(CalDavConnection $connection): CalDavGateway {
        return new HttpCalDavGateway(new Client(['timeout' => 15]), $connection);
    }
}
