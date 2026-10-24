<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

/** Geprüfte Verbindung eines Shops (eine je Organisation), siehe {@see IsShopConnection}. */
interface ShopConnection {
    public function isReady(): bool;

    public function isImportConfirmed(): bool;

    public function sourceId(): ?string;

    public function recordConnectionFailure(string $error): void;

    public function recordConnectionSuccess(): void;
}
