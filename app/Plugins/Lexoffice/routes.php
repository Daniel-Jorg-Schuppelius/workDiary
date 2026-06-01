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
    Route::get('lexoffice-articles/{article}', [LexofficeArticleController::class, 'show'])
        ->name('lexoffice.articles.show');

    // Belege (Lexoffice-Vouchers)
    Route::get('lexoffice-vouchers', [LexofficeVoucherController::class, 'index'])
        ->name('lexoffice.vouchers.index');
    Route::get('lexoffice-vouchers/{voucher}/preview', [LexofficeVoucherController::class, 'preview'])
        ->name('lexoffice.vouchers.preview');
    Route::get('lexoffice-vouchers/{voucher}/file', [LexofficeVoucherController::class, 'file'])
        ->name('lexoffice.vouchers.file');

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
