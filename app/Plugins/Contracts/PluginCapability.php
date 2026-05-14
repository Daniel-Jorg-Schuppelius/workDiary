<?php

namespace App\Plugins\Contracts;

/**
 * Capability identifiers used by plugins to advertise what they can do.
 * Plugins may implement zero or more capability interfaces.
 */
final class PluginCapability {
    /** Plugin can push customer contact data to an external system. */
    public const CONTACT_SYNC = 'contact_sync';

    /** Plugin can transmit recorded times (per customer/project/period). */
    public const TIME_EXPORT = 'time_export';

    /** Plugin can fetch payment status / reconcile data back. */
    public const PAYMENT_SYNC = 'payment_sync';
}
