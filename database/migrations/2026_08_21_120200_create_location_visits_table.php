<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120200_create_location_visits_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abgeleiteter Aufenthalt eines Nutzers innerhalb eines Geofence. `open` solange
 * der Nutzer noch im Radius ist (oder die Spur endet), `closed` nach Verlassen.
 * Geschlossene Besuche werden vom VisitMaterializer in die Inbox überführt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('location_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_geofence_id')->constrained('customer_geofences')->cascadeOnDelete();

            $table->dateTime('entered_at');
            $table->dateTime('left_at')->nullable();
            $table->unsignedInteger('duration_min')->nullable();
            $table->unsignedInteger('sample_count')->default(0);
            $table->string('status', 16)->default('open');
            $table->boolean('materialized')->default(false);

            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'status'], 'lv_idx_org_user_status');
            $table->index(['customer_geofence_id', 'status'], 'lv_idx_geofence_status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('location_visits');
    }
};
