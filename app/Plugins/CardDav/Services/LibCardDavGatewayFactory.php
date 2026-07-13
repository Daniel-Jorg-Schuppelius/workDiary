<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LibCardDavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use App\Models\CardDavConnection;
use App\Plugins\CardDav\Contracts\{CardDavGateway, CardDavGatewayFactory};

/**
 * Standard-Factory (Bauturbo A9): baut je Anbindung ein {@see LibCardDavGateway}.
 * Im Test durch eine Fake-Factory ersetzt (Muster CalDAV-Plugin).
 */
class LibCardDavGatewayFactory implements CardDavGatewayFactory {
    public function for(CardDavConnection $connection): CardDavGateway {
        return new LibCardDavGateway($connection);
    }
}
