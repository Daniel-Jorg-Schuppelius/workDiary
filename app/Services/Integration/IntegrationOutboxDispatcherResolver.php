<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxDispatcherResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Services\AbstractPluginDispatcherResolver;

/**
 * Registry der generischen Outbox-Dispatcher (Feature 055, MVP-114). Muss als
 * Singleton gebunden sein (siehe Basis).
 *
 * @extends AbstractPluginDispatcherResolver<IntegrationOutboxDispatcher>
 */
class IntegrationOutboxDispatcherResolver extends AbstractPluginDispatcherResolver {}
