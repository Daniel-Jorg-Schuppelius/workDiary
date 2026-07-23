<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100000_create_integrity_checks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfhistorie der Quelltext-Integritätsüberwachung (Feature 095, MVP-440):
 * plattformweite Timeline aus Baseline-Erzeugungen und Prüfläufen — jede
 * Zeile ist Audit-Subjekt in der audit_logs-Hash-Kette.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('integrity_checks', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('ran_at');
            $table->string('status', 20);
            $table->string('baseline_source', 10)->nullable();
            $table->string('baseline_root', 64)->nullable();
            $table->unsignedInteger('files_checked')->default(0);
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('modified_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->unsignedInteger('packages_changed_count')->default(0);
            $table->json('findings')->nullable();
            $table->string('findings_hash', 64)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('triggered_by', 10)->default('cli');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'ran_at'], 'ic_status_ran_idx');
            $table->index('ran_at', 'ic_ran_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('integrity_checks');
    }
};
