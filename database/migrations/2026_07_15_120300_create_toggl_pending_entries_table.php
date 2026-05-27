<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_15_120300_create_toggl_pending_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toggl-Zeiteinträge, deren Client/Projekt (noch) keinem workDiary-Kunden bzw.
 * -Projekt zugeordnet ist. Werden beim API-/CSV-Import abgelegt und über die
 * Admin-Inbox einem bestehenden Kunden + Projekt zugewiesen (oder verworfen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('toggl_pending_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('source', 8);              // api | csv
            $table->string('entry_key', 191);         // Idempotenz-Schlüssel (toggl:<id> | csv:<hash>)
            $table->string('client_name')->nullable();
            $table->string('project_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->boolean('billable')->default(false);
            $table->string('user_email')->nullable();
            $table->string('status', 16)->default('open'); // open | imported | dismissed
            $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'entry_key'], 'tpe_unique_entry');
            $table->index(['organization_id', 'status', 'client_name', 'project_name'], 'tpe_group_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('toggl_pending_entries');
    }
};
