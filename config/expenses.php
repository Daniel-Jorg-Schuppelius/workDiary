<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Expense\PaymentMethod;

return [
    /*
     * Default currency for expenses. Falls back to invoicing.default_currency.
     */
    'default_currency' => env('EXPENSES_DEFAULT_CURRENCY', null),

    /*
     * Default tax rate (percent) for new expenses without category override.
     * Falls back to invoicing.default_tax_rate.
     */
    'default_tax_rate' => env('EXPENSES_DEFAULT_TAX_RATE', null),

    /*
     * Maximum upload size in MB per receipt attachment.
     */
    'max_upload_mb' => (int) env('EXPENSES_MAX_UPLOAD_MB', 10),

    /*
     * Allowed receipt mime types (matched in form-request validation).
     */
    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ],

    /*
     * Payment methods accepted in the UI. Subset of PaymentMethod cases.
     * Use [] to allow all defined enum cases.
     *
     * @var list<string>
     */
    'allowed_payment_methods' => [
        PaymentMethod::PrivatePaid->value,
        PaymentMethod::CompanyCard->value,
        PaymentMethod::Cash->value,
        PaymentMethod::BankTransfer->value,
    ],

    /*
     * Roles that receive ExpenseSubmittedNotification. Currently the
     * ApproverResolver filters by User::isAdmin() — these names are
     * reserved for future fine-grained routing.
     *
     * @var list<string>
     */
    'notification_recipient_roles' => [
        'admin',
        'accounting',
    ],
];
