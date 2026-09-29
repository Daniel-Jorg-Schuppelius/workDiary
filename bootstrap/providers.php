<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : providers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Providers\{AppServiceProvider, ContactsServiceProvider, PluginServiceProvider};

return [
    AppServiceProvider::class,
    ContactsServiceProvider::class,
    PluginServiceProvider::class,
];
