<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_30_090000_add_is_travel_itemized_to_customer_billing_agreements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anfahrt im Kundenportal und Anwesenheitsnachweis gesondert ausweisen oder in
 * der Dauer mitführen (Feature 098, MVP-1235). Reine Darstellung: Beträge,
 * Snapshots und Arbeitszeitkonten bleiben unverändert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_billing_agreements', function (Blueprint $table): void {
            $table->boolean('is_travel_itemized')->default(true)->after('holidays_as_weekend');
        });
    }

    public function down(): void {
        Schema::table('customer_billing_agreements', function (Blueprint $table): void {
            $table->dropColumn('is_travel_itemized');
        });
    }
};
