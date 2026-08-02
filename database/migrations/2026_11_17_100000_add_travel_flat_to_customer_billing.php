<?php
/*
 * Created on   : Sat Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_17_100000_add_travel_flat_to_customer_billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 098: Anfahrtspauschale je Zeiteintrag (Minuten aus der Kondition,
 * bewertet mit dem Satz des Eintrags) plus Feiertagsschalter für den Tagtyp.
 * SoftDeletes an den Satzzeilen, damit eine entfernte Kondition den Nachweis
 * (time_entries.customer_billing_rate_id, nullOnDelete) nicht mitreißt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_billing_agreements', function (Blueprint $table): void {
            $table->unsignedSmallInteger('travel_minutes_per_entry')->default(0)->after('workdays_per_week');
            // Tätigkeitskategorien, für die die Anfahrt gilt; NULL/leer = alle.
            $table->json('travel_categories')->nullable()->after('travel_minutes_per_entry');
            // Default false: Bestandskonditionen dürfen sich nicht rückwirkend ändern.
            $table->boolean('holidays_as_weekend')->default(false)->after('travel_categories');
        });

        Schema::table('customer_billing_rates', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->integer('travel_minutes')->default(0)->after('total_minutes');
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            // Snapshot wie hourly_rate: der Wert bleibt am Eintrag stehen, auch
            // wenn die Kondition später geändert wird.
            $table->unsignedSmallInteger('billing_travel_minutes')->default(0)->after('customer_billing_rate_id');
            $table->boolean('billing_travel_manual')->default(false)->after('billing_travel_minutes');
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn(['billing_travel_minutes', 'billing_travel_manual']);
        });

        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->dropColumn('travel_minutes');
        });

        Schema::table('customer_billing_rates', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('customer_billing_agreements', function (Blueprint $table): void {
            $table->dropColumn(['travel_minutes_per_entry', 'travel_categories', 'holidays_as_weekend']);
        });
    }
};
