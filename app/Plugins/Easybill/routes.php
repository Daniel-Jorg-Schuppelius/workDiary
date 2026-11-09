<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\Easybill\Http\Controllers\EasybillVoucherPdfController;
use Illuminate\Support\Facades\Route;

// Original-PDF gespiegelter Belege (MVP-1499); Modul-Gate über das Finanzmodul (`finance.*`).
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->get('finance/easybill/vouchers/{voucher}/pdf', EasybillVoucherPdfController::class)
    ->name('finance.easybill.vouchers.pdf');
