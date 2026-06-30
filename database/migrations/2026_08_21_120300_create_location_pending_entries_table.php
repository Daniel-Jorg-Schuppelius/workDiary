<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120300_create_location_pending_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review-Eingang für aus Geofence-Besuchen abgeleitete Zeitvorschläge (analog
 * {@see App\Models\OpenProjectPendingEntry}). Standortdaten werden nie blind
 * gebucht: erst nach Bestätigung entsteht ein {@see App\Models\TimeEntry}.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('location_pending_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_visit_id')->constrained('location_visits')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->date('suggested_date');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('minutes');
            $table->string('description', 255)->nullable();

            $table->string('status', 16)->default('open');
            $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'status'], 'lpe_idx_org_user_status');
            $table->unique('location_visit_id', 'lpe_uniq_visit');
        });
    }

    public function down(): void {
        Schema::dropIfExists('location_pending_entries');
    }
};
