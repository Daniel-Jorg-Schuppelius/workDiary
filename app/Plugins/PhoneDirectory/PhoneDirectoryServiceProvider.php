<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneDirectoryServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PhoneDirectory;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider: hängt `config.php` unter `plugins.phonedirectory`
 * ein und meldet den {@see PhoneDirectoryResolver} am Rufnummern-Aggregat an
 * (Tag `phone-number-resolvers`, gebunden im Kern-{@see \App\Providers\ContactsServiceProvider}).
 */
class PhoneDirectoryServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return PhoneDirectoryPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->scoped(PhoneDirectoryResolver::class);
        $this->app->tag([PhoneDirectoryResolver::class], 'phone-number-resolvers');
    }
}
