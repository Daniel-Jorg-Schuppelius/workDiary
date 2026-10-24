<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_171100_create_taiga_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taiga-Aufgabenimport (Feature 173, MVP-1343): eine geprüfte
 * Verbindung je Organisation (Instanzschlüssel, Konto, Gesundheit; der Zugang
 * bleibt in den Plugin-Einstellungen) und je Quelle höchstens eine
 * Zuordnung auf ein WorkDiary-Projekt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('taiga_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('tgc_org_unique')->constrained('organizations', indexName: 'tgc_org_fk')->cascadeOnDelete();
            $table->string('source_id', 64)->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('account_id', 128)->nullable();
            $table->string('account_name')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_counters')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'tgc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'tgc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('taiga_project_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tgpl_org_fk')->cascadeOnDelete();
            $table->string('source_project_id', 64);
            $table->string('source_project_key')->nullable();
            $table->string('source_project_name')->nullable();
            $table->foreignId('project_id')->constrained('projects', indexName: 'tgpl_project_fk')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('paused_reason', 16)->nullable();
            $table->timestamp('import_confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users', indexName: 'tgpl_conf_by_fk')->nullOnDelete();
            $table->timestamp('checkpoint_at')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_last_run_complete')->nullable();
            $table->json('last_run_counters')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tgpl_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'source_project_id'], 'tgpl_org_source_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('taiga_project_links');
        Schema::dropIfExists('taiga_connections');
    }
};
