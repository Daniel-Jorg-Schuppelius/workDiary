<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'entity' => [
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'articles' => 'Articles',
        'projects' => 'Projects',
        'users' => 'Users',
        'materials' => 'Materials',
        'vehicles' => 'Vehicles',
        'scheduled_shifts' => 'Shift schedules',
        'tours' => 'Tours',
        'remote_sessions' => 'Remote support sessions',
        'attendances' => 'Attendances',
        'project_times' => 'Project times',
        // MVP-707 (Vollscan H20): Altsystem-Übernahme.
        'invoices' => 'Legacy invoices (open items)',
        'quotes' => 'Quotes',
        'assets' => 'Assets',
        'contact_persons' => 'Contact persons',
        'documents' => 'Documents (ZIP)',
    ],

    'template' => [
        'example_required' => 'Example value (required)',
        'example_optional' => 'Example value (optional)',
        'download' => 'Download sample template',
    ],

    'state' => [
        'preflight' => 'Preflight',
        'awaitingApproval' => 'Awaiting approval',
        'running' => 'Running',
        'succeeded' => 'Succeeded',
        'partial' => 'Partial',
        'failed' => 'Failed',
    ],

    'errorCode' => [
        'required' => 'Required field missing',
        'format' => 'Format error',
        'unique' => 'Value not unique',
        'fkMissing' => 'Reference not found',
        'tooLong' => 'Value too long',
        'outOfRange' => 'Value out of range',
        'persist' => 'Persistence error',
        'headerMissing' => 'Column missing',
        'headerUnknown' => 'Column unknown',
        'periodLocked' => 'Period locked',
        'skipped' => 'Skipped',
        'blocked' => 'Blocked',
    ],

    'error' => [
        'required' => 'Required field :field is missing.',
        'tooLong' => 'Field :field exceeds maximum length of :max characters.',
        'header' => [
            'missing' => 'Required column :column is missing in CSV header.',
            'duplicate' => 'Column :column appears multiple times.',
        ],
        'format' => [
            'default' => 'Field :field has an invalid format (:reason).',
            'email' => 'Not a valid email address.',
            'country' => 'Country code must be 2-3 uppercase letters (ISO 3166-1).',
            'currency' => 'Currency code must be 3 uppercase letters (ISO 4217).',
            'enum' => 'Value is not a valid status.',
            'parse' => 'File could not be parsed: :reason',
            'xlsxUnreadable' => 'The Excel file is corrupted or not a valid XLSX format.',
            'xlsxEmpty' => 'The first worksheet of the Excel file contains no rows.',
            'date' => 'Not a valid date (expected e.g. "28.05.2026, 09:42:09").',
            'time' => 'Not a valid time (expected HH:MM).',
            'status' => 'Value is not a valid status.',
            'amount' => 'Not a valid amount.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Row limit (:max) exceeded — remainder ignored.',
            'contactPersons' => 'More than :max contact persons per customer/supplier are not supported.',
        ],
        'fkMissing' => [
            'customer' => 'No customer with number :number found.',
            'supplier' => 'No supplier with number :number found.',
            'asset' => 'No asset with number :number found.',
            'article' => 'No article with number :number found.',
            'projectNumber' => 'No project with number :number found.',
            'customerName' => 'No unique customer named ":value" found.',
            'user' => 'No user with email :value found.',
            'project' => 'No project ":value" found — row moved to the assignment inbox.',
        ],
        // MVP-707: Altsystem-Übernahme (Rechnungshoheit, Altrechnungen, Dokument-ZIP).
        'blocked' => [
            'invoiceSovereignty' => 'Invoicing is owned by :program — local legacy invoices are blocked for this customer.',
        ],
        'invoice' => [
            'amountMissing' => 'Gross or net amount (with tax rate) is missing.',
            'paidExceedsTotal' => 'Paid amount (:paid) exceeds the invoice total (:total).',
            'numberTaken' => 'Invoice number :number is already in use.',
        ],
        'document' => [
            'manifestMissing' => 'The ZIP file does not contain a manifest.csv.',
            'fileMissing' => 'File ":file" is not part of the ZIP.',
            'extension' => 'File extension ":ext" is not allowed.',
            'mime' => 'File content (:mime) is not allowed.',
            'targetType' => 'Target type must be customer, project or asset.',
            'noContent' => 'Documents can only be imported via the ZIP import (manifest.csv + files).',
            'zipUnreadable' => 'ZIP file could not be read: :reason',
            'tooLarge' => 'File ":file" exceeds the size limit of :max MB.',
            'noActor' => 'Import run without triggering user — documents need a creator.',
        ],
        'persist' => [
            'noBookingUser' => 'No bookable user found in the organisation.',
        ],
        // MVP-438: GoBD lock — no silent overwrite of reviewed periods.
        'periodLocked' => [
            'attendance' => 'Day :date is locked by day-close or month approval — row skipped.',
            'projectTime' => 'Period :date is already closed/exported — row skipped.',
        ],
        // MVP-438: iCal notice rows (deliberately conservative mapping).
        'ical' => [
            'allDay' => 'All-day event ":event" skipped (cannot count as attendance).',
            'noTime' => 'Event ":event" without a time skipped.',
            'category' => 'Event ":event" outside the category allowlist skipped.',
            'transparent' => 'Event ":event" marked free/out-of-office skipped.',
            'recurring' => 'Recurring event ":event": only the base instance was imported (series expansion comes later).',
            'unsupportedEntity' => 'iCal import is not supported for this import type.',
        ],
    ],

    // MVP-707: Upload-Hinweise je Dateiart + Texte der Altrechnungs-Übernahme.
    'upload' => [
        'csv' => 'CSV, Excel or iCal file (.csv, .xlsx, .ics, max. :mb MB, :rows rows)',
        'zip' => 'ZIP file with manifest.csv and the document files (.zip, max. :mb MB, :entries files)',
        'zipHint' => 'Each manifest.csv row (template above) references one file inside the ZIP and assigns it to a customer, project or asset.',
    ],
    'legacy' => [
        'position' => 'Legacy system takeover — invoice :number',
        'note' => 'Legacy invoice taken over from :source (opening open item, no journal entry).',
    ],
];
