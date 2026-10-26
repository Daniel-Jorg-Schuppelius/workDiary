<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_140000_add_provider_unit_price_to_resale_subscriptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 185 (MVP-1418): Einkaufspreis laut letztem Anbieter-Export je Abo.
 * Der Import zieht `purchase_unit_price` nur nach, solange er diesem Wert
 * entspricht — ein von Hand gepflegter Preis bleibt stehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_subscriptions', function (Blueprint $t): void {
            if (! Schema::hasColumn('resale_subscriptions', 'provider_unit_price')) {
                $t->decimal('provider_unit_price', 12, 4)->nullable()->after('purchase_unit_price');
            }
        });
    }

    public function down(): void {
        Schema::table('resale_subscriptions', function (Blueprint $t): void {
            $t->dropColumn('provider_unit_price');
        });
    }
};
