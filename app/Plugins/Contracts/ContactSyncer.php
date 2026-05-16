<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactSyncer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Customer;

/**
 * Plugins implementing this contract can synchronize a workDiary Customer
 * with the external system (e.g. as a Lexoffice contact). The returned string
 * is the external id, persisted via ExternalReference.
 */
interface ContactSyncer
{
    public function pushContact(Customer $customer): string;
}
