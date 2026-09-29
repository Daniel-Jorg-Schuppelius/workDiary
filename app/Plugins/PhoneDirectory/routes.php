<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\PhoneDirectory\Http\Controllers\PhoneDirectoryController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Route: Stammdaten-Anreicherung aus der Kundenakte. Plan-/Modul-
 * Gate wie in den Kern-Routen (customers.* ist einem Modul zugeordnet).
 */
Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])->group(function (): void {
    Route::post('customers/{customer}/phonedirectory/fill', [PhoneDirectoryController::class, 'fillCustomer'])
        ->name('customers.phonedirectory.fill');
});
