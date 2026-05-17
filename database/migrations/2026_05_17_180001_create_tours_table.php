<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_180001_create_tours_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()
                ->constrained('vehicles')->nullOnDelete();

            $table->date('tour_date')->index();
            $table->string('name')->nullable();

            $table->string('start_address')->nullable();
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->string('end_address')->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();

            $table->decimal('planned_distance_km', 10, 2)->default(0);
            $table->unsignedInteger('planned_duration_minutes')->default(0);
            $table->longText('route_geometry')->nullable();

            $table->string('status', 16)->default('draft');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'tour_date']);
            $table->index(['user_id', 'tour_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
