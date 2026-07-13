<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shipping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Shipping & Logistics',
    'intro' => 'Carrier connections for shipping labels and shipment tracking (DHL Parcel, UPS, FedEx). One connection per carrier and organization; credentials are stored encrypted.',

    'form_heading' => 'Add / edit connection',
    'form_hint' => 'Pick the carrier and enter its credentials. Saving again with the same carrier updates the existing connection.',
    'secret_hint' => 'Password and API key are stored encrypted and never shown again. Leave them blank when editing to keep the stored values.',
    'connections_heading' => 'Existing connections',
    'no_connections' => 'No carrier connection configured yet.',

    'field' => [
        'carrier' => 'Carrier',
        'name' => 'Label',
        'username' => 'User / client ID',
        'password' => 'Password / client secret',
        'api_key' => 'API key (DHL only: dhl-api-key)',
        'billing_number' => 'Billing/account number',
        'sandbox' => 'Sandbox / test environment',
        'active' => 'Active',
        'weight_grams' => 'Weight (g)',
        'length_cm' => 'Length (cm)',
        'width_cm' => 'Width (cm)',
        'height_cm' => 'Height (cm)',
    ],

    'label_short' => 'Shipping',

    'col' => [
        'mode' => 'Mode',
        'status' => 'Status',
    ],

    'mode' => [
        'sandbox' => 'Sandbox',
        'production' => 'Production',
    ],

    'status_label' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'action' => [
        'save' => 'Save',
        'disconnect' => 'Deactivate',
        'create' => 'Ship',
    ],

    'flash' => [
        'saved' => 'Carrier connection saved.',
        'disconnected' => 'Carrier connection deactivated.',
        'credentials_required' => 'A new connection requires user/client ID and password/client secret (DHL additionally: API key).',
        'no_recipient' => 'The delivery has no customer as recipient.',
        'already_created' => 'A shipment already exists for this delivery.',
        'no_connection' => 'No active connection is configured for the selected carrier.',
        'label_created' => 'Shipment created and label retrieved.',
        'label_failed' => 'Could not create the shipping label: :reason',
    ],

    'notify' => [
        'delivery_problem' => [
            'title' => 'Delivery problem with a shipment',
            'message' => 'Shipment :tracking (:carrier) reports a delivery problem.',
        ],
    ],

    // Shipment status (ShipmentStatus).
    'status' => [
        'draft' => 'Draft',
        'labeled' => 'Label created',
        'in_transit' => 'In transit',
        'delivered' => 'Delivered',
        'problem' => 'Delivery problem',
        'cancelled' => 'Cancelled',
    ],
];
