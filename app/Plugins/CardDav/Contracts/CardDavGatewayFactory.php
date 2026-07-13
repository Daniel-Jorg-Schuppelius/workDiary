<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Contracts;

use App\Models\CardDavConnection;

/**
 * Baut je Anbindung ein {@see CardDavGateway}. Im Test durch eine
 * Fake-Factory ersetzt (Muster CalDAV-Plugin).
 */
interface CardDavGatewayFactory {
    public function for(CardDavConnection $connection): CardDavGateway;
}
