<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderGaebResponseReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Contracts;

use App\Services\Procurement\Dto\OrderResponseData;
use RuntimeException;

/**
 * GAEB-Rücklauf des Händlers (MVP-1300: X97 Auftragsbestätigung, X94
 * Preisangebot): definiert vom Einkauf, gebunden vom Baumodul
 * ({@see \App\Services\Gaeb\GaebOrderResponseReader}). Die Null-Bindung liest
 * nichts.
 */
interface PurchaseOrderGaebResponseReader {
    /**
     * Null, wenn der Inhalt kein GAEB-Handelsbeleg ist.
     *
     * @throws RuntimeException bei einem Handelsbeleg, der keine Antwort ist (X93, X96)
     */
    public function read(string $content): ?OrderResponseData;
}
