<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Time clock terminals',
    'intro' => 'Fixed RFID/NFC terminals clock employees without a work device in and out. The events run into the same attendance logic as browser stamps (corrections, reports). Device tokens and badge IDs are stored hashed only.',

    'new_heading' => 'Terminal ingest URL',
    'new_hint' => 'Enter it into the terminal now — the token is shown only this once.',

    'terminals_heading' => 'Terminals',
    'no_terminals' => 'No terminal registered yet.',
    'badges_heading' => 'Badges',
    'no_badges' => 'No badge assigned yet.',

    'field' => [
        'name' => 'Label',
        'name_placeholder' => 'e.g. Hall North',
        'site' => 'Site',
        'no_site' => '— no site —',
    ],

    'badge' => [
        'user' => 'Employee',
        'label' => 'Label',
        'uid' => 'Badge ID',
        'uid_placeholder' => 'RFID/NFC UID',
        'uid_help' => 'Stored as a hash only (no plaintext ID).',
        'validity' => 'Validity',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'outside_validity' => 'outside window',
    ],

    'action' => [
        'register' => 'Register',
        'disable' => 'Disable',
        'assign' => 'Assign',
        'revoke' => 'Revoke',
        'rotate' => 'Rotate token',
        'rotate_help' => 'Generate a new device token — the old one becomes invalid immediately.',
    ],

    'col' => [
        'status' => 'Status',
        'status_display' => 'Status display',
        'last_seen' => 'Last seen',
    ],

    'status_display' => [
        'on' => 'On',
        'off' => 'Off',
        'help' => 'Shows flex balance/remaining vacation on the device after stamping (visible to bystanders) — off by default.',
    ],

    'buffer' => [
        'label' => 'Buffer',
        'help' => 'Offline events reported by the terminal that have not been transmitted yet.',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Disabled',
        'revoked' => 'Revoked',
    ],

    'flash' => [
        'registered' => 'Terminal registered.',
        'terminal_disabled' => 'Terminal disabled.',
        'badge_assigned' => 'Badge assigned.',
        'badge_revoked' => 'Badge revoked.',
        'badge_taken' => 'This badge ID is already assigned.',
        'token_rotated' => 'Device token rotated — new ingest URL shown once.',
        'status_enabled' => 'Status display enabled.',
        'status_disabled' => 'Status display disabled.',
    ],
    'kiosk' => [
        'pin_toggle' => 'Forgot your badge? Clock with PIN',
        'pin_submit' => 'Clock',
        'heading' => 'Kiosk address',
        'hint' => 'Open in a tablet browser — the tablet becomes a time clock terminal. Contains the same device token; shown only once.',
        'title' => 'Time clock',
        'intro' => 'Hold your badge to the reader.',
        'mode' => 'Booking type',
        'mode_work' => 'In / Out',
        'mode_break' => 'Break',
        'badge_label' => 'Badge',
        'nfc_start' => 'Use this device\'s NFC',
        'nfc_active' => 'NFC is reading',
        'flex_balance' => 'Flexitime:',
        'status' => [
            'invalid_pin' => 'Personnel number or PIN invalid',
            'clocked_in' => 'Clocked in',
            'clocked_out' => 'Clocked out',
            'break_started' => 'Break started',
            'break_ended' => 'Break ended',
            'noop' => 'No open attendance',
            'skipped' => 'Already recorded',
            'unknown_badge' => 'Unknown badge',
            'rejected' => 'Booking rejected',
            'invalid_token' => 'Terminal disabled',
            'unavailable' => 'Clocking is not possible right now',
            'network' => 'No connection — please try again',
            'nfc_unavailable' => 'NFC is not available on this device',
            'error' => 'Booking failed',
        ],
    ],
    'checkpoint' => [
        'heading' => 'Check-in points (QR/NFC)',
        'intro' => 'A code at a location or vehicle: staff scan it with their own device and clock while signed in. The same address can be written to an NFC sticker.',
        'empty' => 'No check-in points yet.',
        'action' => [
            'create' => 'Create check-in point',
            'qr' => 'Print QR code',
            'enable' => 'Enable',
        ],
        'field' => [
            'kind' => 'Type',
            'location' => 'Location / vehicle',
            'vehicle' => 'Vehicle',
            'radius' => 'Radius (m)',
            'location_check' => 'Location check (optional)',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
        ],
        'help' => [
            'site' => 'Only for the type “Location”.',
            'vehicle' => 'Required for the type “Vehicle”.',
            'location_check' => 'A code can be photographed. With a radius, the device must be nearby when clocking; without own coordinates, those of the location apply. The position is not stored.',
        ],
        'error' => [
            'radius_without_center' => 'The radius needs a location: enter coordinates or choose a location with geo coordinates.',
            'vehicle' => 'The vehicle was not found.',
        ],
        'flash' => [
            'created' => 'Check-in point created.',
            'enabled' => 'Check-in point enabled.',
            'disabled' => 'Check-in point disabled.',
        ],
        'qr' => [
            'alt' => 'QR code for check-in “:name”',
            'hint' => 'Scan with your phone, sign in and confirm clock-in or clock-out.',
            'nfc_hint' => 'For an NFC sticker, write this address to the sticker as a web address (URL) using an NFC app.',
        ],
    ],
    'pin' => [
        'heading' => 'Terminal PINs',
        'intro' => 'Forgot your badge? Personnel number and PIN still allow clocking at the terminal and kiosk. Only a hash is stored; after 5 failed attempts the PIN is locked for 15 minutes.',
        'empty' => 'No PINs assigned yet.',
        'action' => [
            'set' => 'Set PIN',
            'unlock' => 'Unlock',
            'remove' => 'Remove',
        ],
        'field' => [
            'pin' => 'PIN',
            'pin_confirmation' => 'Repeat PIN',
            'personnel_number' => 'Personnel number',
        ],
        'help' => [
            'dialog' => '4 to 8 digits. The person learns the PIN from you — it cannot be viewed afterwards.',
            'personnel_number' => 'Only people with a personnel number — it is the second part of the terminal sign-in.',
        ],
        'status' => [
            'locked_until' => 'locked until :time',
        ],
        'confirm' => [
            'remove' => 'Really remove the PIN? The person can then only clock with a badge.',
        ],
        'error' => [
            'format' => 'The PIN must consist of :min to :max digits.',
            'personnel_number' => 'The person has no personnel number — without it the PIN cannot be used at the terminal.',
        ],
        'flash' => [
            'set' => 'PIN set.',
            'unlocked' => 'PIN unlocked.',
            'removed' => 'PIN removed.',
        ],
    ],
];
