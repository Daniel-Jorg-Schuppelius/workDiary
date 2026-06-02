<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120600_add_travel_billing_fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anfahrt-/Reisekosten-Abrechnung bei Touren:
 * - tours.travel_billed: Anfahrt der Tour bereits berechnet (Sperre gegen
 *   Doppelberechnung, analog material_usages.billed / time_entries.exported).
 * - invoice_items.tour_id: Quelle einer Anfahrt-Position.
 * - customers.travel_settings: Kunden-Override der globalen Anfahrt-Einstellung
 *   (JSON; null = global erben).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tours', function (Blueprint $table): void {
            $table->boolean('travel_billed')->default(false)->after('status');
            $table->index('travel_billed');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('tour_id')->nullable()->after('material_usage_id')
                ->constrained('tours')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->json('travel_settings')->nullable()->after('billing_grouping_gap_minutes');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tour_id');
        });

        Schema::table('tours', function (Blueprint $table): void {
            $table->dropIndex(['travel_billed']);
            $table->dropColumn('travel_billed');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('travel_settings');
        });
    }
};
