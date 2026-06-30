<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120000_create_customer_geofences_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geofence je Kunde (optional je Standort). Ein Aufenthalt innerhalb des
 * Radius wird zu einem {@see App\Models\Location\LocationVisit} und – nach
 * Bestätigung in der Inbox – zu einer Zeitbuchung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_geofences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->string('label', 160);
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);
            $table->unsignedSmallInteger('radius_m')->default(100);
            $table->unsignedSmallInteger('min_dwell_minutes')->default(5);
            $table->unsignedSmallInteger('gap_merge_minutes')->default(10);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id', 'is_active'], 'cgf_idx_org_customer');
            $table->index(['organization_id', 'is_active'], 'cgf_idx_org_active');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_geofences');
    }
};
