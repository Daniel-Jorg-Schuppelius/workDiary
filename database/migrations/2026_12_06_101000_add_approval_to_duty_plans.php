<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101000_add_approval_to_duty_plans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-525 (Feature 103): Dienstplan-Genehmigungsworkflow — beantragt →
 * genehmigt/veröffentlicht — plus unveränderlicher Archiv-Snapshot der
 * „amtlichen" Fassung zum Genehmigungszeitpunkt (Q1-Konzept).
 */
return new class extends Migration {
    public function up(): void {
        // MariaDB-Zweig: die Status-Spalte ist ein natives ENUM — ohne
        // Erweiterung truncated 'submitted' (Strict-Regel, s. Memory).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                "ALTER TABLE duty_plans MODIFY status ENUM('draft', 'submitted', 'published') NOT NULL DEFAULT 'draft'",
            );
        }

        Schema::table('duty_plans', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users', indexName: 'dp_submitted_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'dp_approved_fk')->nullOnDelete();
            $table->json('archive_snapshot')->nullable();
        });
    }

    public function down(): void {
        Schema::table('duty_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['submitted_at', 'approved_at', 'archive_snapshot']);
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                "ALTER TABLE duty_plans MODIFY status ENUM('draft', 'published') NOT NULL DEFAULT 'draft'",
            );
        }
    }
};
