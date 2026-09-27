<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_290000_add_classification_fields_to_asset_finance_contracts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-947: Eingaben und Snapshot der IFRS-16-/HGB-Einschätzung am Leasingvertrag. */
return new class extends Migration {
    public function up(): void {
        Schema::table('asset_finance_contracts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('useful_life_months')->nullable()->after('purchase_option_amount');
            $table->decimal('asset_value_amount', 14, 2)->nullable()->after('useful_life_months');
            $table->boolean('is_special_lease')->default(false)->after('asset_value_amount');
            $table->json('classification_snapshot')->nullable()->after('is_special_lease');
        });
    }

    public function down(): void {
        Schema::table('asset_finance_contracts', function (Blueprint $table): void {
            $table->dropColumn(['useful_life_months', 'asset_value_amount', 'is_special_lease', 'classification_snapshot']);
        });
    }
};
