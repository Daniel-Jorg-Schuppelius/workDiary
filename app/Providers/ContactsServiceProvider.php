<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactsServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contacts\ExternalPhoneContactDirectory;
use Illuminate\Support\ServiceProvider;

/**
 * Kern-Bindung des provider-neutralen Rufnummern-Aggregats. Die einzelnen
 * Quellen tragen sich selbst bei: aufzählbare Verzeichnisse über das Tag
 * `external-phone-contact-sources` (Lexoffice, Microsoft 365), Rückwärts-
 * Auskünfte über `phone-number-resolvers` (Telefonauskunft-Plugin). So bleibt
 * das Aggregat verfügbar (auch für CTI), ohne dass der Kern die Plugins kennt.
 */
class ContactsServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->scoped(
            ExternalPhoneContactDirectory::class,
            fn($app): ExternalPhoneContactDirectory => new ExternalPhoneContactDirectory(
                $app->tagged('external-phone-contact-sources'),
                $app->tagged('phone-number-resolvers'),
            ),
        );
    }
}
