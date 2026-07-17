<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInventoryDispatcherResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Services\AbstractPluginDispatcherResolver;

/**
 * Registry der externen Bestands-Dispatcher (Feature 048, MVP-072). Muss als
 * Singleton gebunden sein (siehe Basis).
 *
 * @extends AbstractPluginDispatcherResolver<ExternalInventoryDispatcher>
 */
class ExternalInventoryDispatcherResolver extends AbstractPluginDispatcherResolver {}
