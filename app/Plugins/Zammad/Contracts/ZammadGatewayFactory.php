<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadGatewayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Contracts;

use App\Models\ZammadConnection;

/**
 * Erzeugt je Anbindung ein {@see ZammadGateway} (Feature 060, MVP-129). Über
 * den Container gebunden, damit Plugin/Command/Webhook den Gateway nicht selbst
 * konstruieren müssen — Tests ersetzen die Factory durch eine Variante, die
 * einen gemockten Gateway liefert (kein echter HTTP-Verkehr).
 */
interface ZammadGatewayFactory {
    public function for(ZammadConnection $connection): ZammadGateway;
}
