<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_120002_create_travel_logs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trip records (Fahrtenbuch / Anfahrten):
 *
 *  - Captures distance + addresses + vehicle for mileage reimbursement.
 *  - Optionally linked to a project/customer/task and to a TimeEntry that
 *    represents the time portion (kind=travel, activity_type=travel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('tasks')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()
                ->constrained('attendances')->nullOnDelete();

            $table->date('date');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);

            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->decimal('from_lat', 10, 7)->nullable();
            $table->decimal('from_lng', 10, 7)->nullable();
            $table->decimal('to_lat', 10, 7)->nullable();
            $table->decimal('to_lng', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2)->default(0);

            // company | private | public_transport | bicycle | foot | other
            $table->string('vehicle', 32)->default('private');
            $table->string('vehicle_label', 64)->nullable();
            $table->string('purpose')->nullable();
            $table->boolean('round_trip')->default(false);

            $table->boolean('reimbursable')->default(true);
            $table->decimal('rate_per_km', 8, 4)->nullable();
            $table->decimal('reimbursement_total', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['organization_id', 'date']);
            $table->index('project_id');
            $table->index('customer_id');
            $table->index('reimbursable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_logs');
    }
};
