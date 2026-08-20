<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Lexoffice\Http\Controllers\Admin\LexofficeConflictInboxController;
use App\Plugins\Lexoffice\Http\Controllers\{LexofficeArticleController, LexofficeCustomerController, LexofficeInvoiceController, LexofficeVoucherController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen. Werden vom {@see \App\Plugins\Lexoffice\LexofficeServiceProvider}
 * geladen — der Core kennt diese URLs nicht direkt.
 *
 * Alle Routen leben unterhalb des bestehenden auth-Middleware-Stacks; die
 * eigentliche Berechtigung wird in den Controllern per Gate geprüft.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    // Kunden-bezogen
    Route::post('customers/lexoffice/push-all', [LexofficeCustomerController::class, 'bulkPush'])
        ->name('customers.lexoffice.push-all');
    Route::post('customers/{customer}/lexoffice/contact', [LexofficeCustomerController::class, 'pushContact'])
        ->name('customers.lexoffice.contact');
    Route::post('customers/{customer}/lexoffice/time-export', [LexofficeCustomerController::class, 'exportTime'])
        ->name('customers.lexoffice.time-export');
    Route::post('customers/{customer}/lexoffice/sync-vouchers', [LexofficeVoucherController::class, 'syncCustomer'])
        ->name('customers.lexoffice.sync-vouchers');
    Route::post('suppliers/{supplier}/lexoffice/sync-vouchers', [LexofficeVoucherController::class, 'syncSupplier'])
        ->name('suppliers.lexoffice.sync-vouchers');

    // Rechnungs-bezogen
    Route::post('invoices/{invoice}/lexoffice/publish', [LexofficeInvoiceController::class, 'publish'])
        ->name('invoices.lexoffice.publish');
    Route::get('invoices/{invoice}/lexoffice/pdf', [LexofficeInvoiceController::class, 'pdf'])
        ->name('invoices.lexoffice.pdf');

    // Produkte & Leistungen (Lexoffice-Artikel)
    Route::get('lexoffice-articles', [LexofficeArticleController::class, 'index'])
        ->name('lexoffice.articles.index');
    Route::post('lexoffice-articles/sync', [LexofficeArticleController::class, 'sync'])
        ->name('lexoffice.articles.sync');
    Route::get('lexoffice-articles/{article}/details', [LexofficeArticleController::class, 'details'])
        ->name('lexoffice.articles.details');

    // Belege (Lexoffice-Vouchers)
    // MVP-549: Die eigene Belegliste ist im Belegfluss aufgegangen — die
    // Herkunft ist dort ein Filter, keine eigene Seite.
    Route::get('lexoffice-vouchers', [\App\Http\Controllers\Billing\DocumentFeedController::class, 'fromVouchers'])
        ->name('lexoffice.vouchers.index');
    Route::post('lexoffice-vouchers/sync', [LexofficeVoucherController::class, 'sync'])
        ->name('lexoffice.vouchers.sync');
    Route::get('lexoffice-vouchers/{voucher}/preview', [LexofficeVoucherController::class, 'preview'])
        ->name('lexoffice.vouchers.preview');
    Route::get('lexoffice-vouchers/{voucher}/file', [LexofficeVoucherController::class, 'file'])
        ->name('lexoffice.vouchers.file');
    Route::post('lexoffice-vouchers/{voucher}/dunning', [LexofficeVoucherController::class, 'createDunning'])
        ->name('lexoffice.vouchers.dunning'); // 045 Mahnung aus überfälliger Rechnung

    // Konflikt-Inbox
    Route::get('admin/lexoffice/conflicts', [LexofficeConflictInboxController::class, 'index'])
        ->name('admin.lexoffice.conflicts.index');
    Route::post('admin/lexoffice/conflicts/{conflict}/resolve-local', [LexofficeConflictInboxController::class, 'resolveLocal'])
        ->name('admin.lexoffice.conflicts.resolve-local');
    Route::post('admin/lexoffice/conflicts/{conflict}/resolve-remote', [LexofficeConflictInboxController::class, 'resolveRemote'])
        ->name('admin.lexoffice.conflicts.resolve-remote');
    Route::post('admin/lexoffice/conflicts/{conflict}/dismiss', [LexofficeConflictInboxController::class, 'dismiss'])
        ->name('admin.lexoffice.conflicts.dismiss');
});

// Sessionloser Webhook-Empfang (Audit 2026-08, Welle 1.3): Autorisierung über
// das URL-Token je Organisation (+ optionale RSA-Signaturprüfung im Controller).
Route::middleware(['api', 'throttle:lexoffice-webhook'])
    ->post('api/webhooks/lexoffice/{organization}/{token}', \App\Plugins\Lexoffice\Http\Controllers\LexofficeWebhookController::class)
    ->whereNumber('organization')
    ->name('api.webhooks.lexoffice');
