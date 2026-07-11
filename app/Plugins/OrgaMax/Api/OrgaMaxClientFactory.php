<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Api;

use App\Models\OrgaMaxConnection;
use App\Plugins\Support\PluginHttpFactory;

/**
 * Baut den typisierten Client je Verbindung. Die {@see PluginHttpFactory}
 * wird bewusst ERST zur Aufrufzeit aus dem Container gelöst: der Dispatcher
 * wird beim Plugin-Boot registriert, Tests binden den Fake-Transport
 * (FakePluginHttp) aber erst danach.
 */
class OrgaMaxClientFactory {
    public function __construct(private readonly OrgaMaxTokenService $tokens) {}

    public function for(OrgaMaxConnection $connection): OrgaMaxClient {
        return new OrgaMaxClient(
            app(PluginHttpFactory::class),
            $this->tokens,
            $connection,
            (string) config('plugins.orgamax.base_url'),
        );
    }
}
