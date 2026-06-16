<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_120000_create_openproject_pending_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OpenProject-Zeiteinträge, deren Projekt (noch) keinem workDiary-Projekt
 * zugeordnet ist. Werden beim API-Import abgelegt und über die Admin-Inbox
 * einem bestehenden Projekt zugewiesen (oder verworfen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('openproject_pending_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('entry_key', 191);                    // Idempotenz-Schlüssel (openproject:te:<id>)
            $table->string('project_external_id', 64)->nullable();
            $table->string('project_name')->nullable();
            $table->string('work_package_external_id', 64)->nullable();
            $table->string('work_package_subject')->nullable();
            $table->string('description')->nullable();
            $table->date('spent_on');
            $table->unsignedInteger('minutes')->default(0);
            $table->string('user_external_id', 64)->nullable();
            $table->string('user_name')->nullable();
            $table->string('status', 16)->default('open');       // open | imported | dismissed
            $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'entry_key'], 'ope_unique_entry');
            $table->index(['organization_id', 'status', 'project_external_id'], 'ope_group_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('openproject_pending_entries');
    }
};
