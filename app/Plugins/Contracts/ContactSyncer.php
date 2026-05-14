<?php

namespace App\Plugins\Contracts;

use App\Models\Customer;

/**
 * Plugins implementing this contract can synchronize a workDiary Customer
 * with the external system (e.g. as a Lexoffice contact). The returned string
 * is the external id, persisted via ExternalReference.
 */
interface ContactSyncer {
    public function pushContact(Customer $customer): string;
}
