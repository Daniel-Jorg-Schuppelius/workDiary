<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120100_create_location_points_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rohe GPS-Spur eines Nutzers (OwnTracks/Traccar, Google-Timeline-Import oder
 * Browser-Stempel). PII – mit Aufbewahrungsfrist (location:purge-points) und
 * nur dem erfassenden Nutzer zugänglich. `processed_at` markiert Punkte, die
 * bereits durch den VisitBuilder gelaufen sind.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('location_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('recorded_at');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->string('source', 24)->default('owntracks');
            $table->uuid('ingest_batch_id')->nullable();
            $table->dateTime('processed_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'recorded_at'], 'lp_idx_org_user_time');
            $table->index(['user_id', 'processed_at'], 'lp_idx_user_unprocessed');
        });
    }

    public function down(): void {
        Schema::dropIfExists('location_points');
    }
};
