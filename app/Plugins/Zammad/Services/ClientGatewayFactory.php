<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Services;

use App\Models\ZammadConnection;
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};

/**
 * Standard-Factory (Feature 060): baut je Anbindung einen echten
 * {@see ZammadClientGateway} über den offiziellen Zammad-Client. Im Test durch
 * eine Fake-Factory ersetzt (kein HTTP).
 */
class ClientGatewayFactory implements ZammadGatewayFactory {
    public function for(ZammadConnection $connection): ZammadGateway {
        return ZammadClientGateway::forConnection($connection);
    }
}
