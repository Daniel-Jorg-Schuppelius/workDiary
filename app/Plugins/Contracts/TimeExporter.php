<?php

namespace App\Plugins\Contracts;

use App\Models\Customer;
use Carbon\CarbonImmutable;

/**
 * Plugins implementing this contract can transmit recorded times for a
 * customer within a date range to the external system (e.g. as a Lexoffice
 * voucher representing a service transaction).
 */
interface TimeExporter {
    /**
     * Export all (matching) time entries of $customer in [$from, $to].
     *
     * @return array{external_id: string, external_type: string, payload?: array<string, mixed>}
     */
    public function exportCustomerTime(Customer $customer, CarbonImmutable $from, CarbonImmutable $to): array;
}
