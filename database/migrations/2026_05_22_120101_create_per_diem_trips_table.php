<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120101_create_per_diem_trips_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-Diem-Reise (Trip-Header):
 *
 *  - Eine Reise einer Person mit definiertem Zeitraum + Land + Ort.
 *  - workplace_key dient der 3-Monats-Regel (gleiche Tätigkeitsstätte).
 *  - status: draft | converted | cancelled. Bei converted ist expense_id gesetzt.
 *  - travel_log_id optional als Herkunfts-Beleg (Generator).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('per_diem_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('travel_log_id')->nullable()
                ->constrained('travel_logs')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()
                ->constrained('expenses')->nullOnDelete();

            $table->string('country', 2)->default('DE');
            $table->string('purpose', 255);
            $table->string('location', 255);
            $table->string('workplace_key', 100)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->boolean('accommodation_provided')->default(false);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['organization_id', 'status']);
            $table->index(['workplace_key']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('per_diem_trips');
    }
};
