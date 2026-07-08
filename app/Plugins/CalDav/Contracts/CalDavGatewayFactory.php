<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Contracts;

use App\Models\CalDavConnection;

/**
 * Erzeugt je Anbindung ein {@see CalDavGateway} (Feature 058). Über den Container
 * gebunden — Tests ersetzen die Factory durch eine Variante mit gemocktem Gateway
 * (kein HTTP-Verkehr).
 */
interface CalDavGatewayFactory {
    public function for(CalDavConnection $connection): CalDavGateway;
}
