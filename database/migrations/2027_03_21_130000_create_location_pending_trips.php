<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_21_130000_create_location_pending_trips.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 146, MVP-1185: Fahrtvorschläge aus der Spur zwischen zwei Besuchen
 * (E3). Nur Eckdaten — die Rohpunkte unterliegen weiter der Aufbewahrung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('location_pending_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'lpt_org_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'lpt_user_fk')->cascadeOnDelete();
            $table->foreignId('from_visit_id')->nullable()->constrained('location_visits', indexName: 'lpt_from_visit_fk')->nullOnDelete();
            $table->foreignId('to_visit_id')->nullable()->constrained('location_visits', indexName: 'lpt_to_visit_fk')->nullOnDelete();
            $table->string('from_label', 160)->nullable();
            $table->string('to_label', 160)->nullable();
            $table->decimal('from_lat', 10, 7)->nullable();
            $table->decimal('from_lng', 10, 7)->nullable();
            $table->decimal('to_lat', 10, 7)->nullable();
            $table->decimal('to_lng', 10, 7)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->decimal('distance_km', 8, 2);
            $table->string('distance_source', 16)->default('track');
            $table->unsignedSmallInteger('point_count')->default(0);
            $table->string('status', 16)->default('open');
            $table->foreignId('travel_log_id')->nullable()->constrained('travel_logs', indexName: 'lpt_travel_log_fk')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users', indexName: 'lpt_resolved_by_fk')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'status'], 'lpt_org_user_status_idx');
            $table->unique(['from_visit_id', 'to_visit_id'], 'lpt_visits_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('location_pending_trips');
    }
};
