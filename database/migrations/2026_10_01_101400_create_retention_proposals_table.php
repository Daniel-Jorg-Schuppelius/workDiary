<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101400_create_retention_proposals_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufbewahrungs-Review (Restpunkt 66): Der Retention-Scan erzeugt
 * Lösch-VORSCHLÄGE statt direkt zu löschen; ein Admin bestätigt gebündelt
 * (zweistufig approve → purge), erst dann läuft der Lösch-Job. Restpunkt 67:
 * Fristen je Rechtsraum (organizations.legal_region + config/retention.php).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('retention_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'rp_org_fk')
                ->cascadeOnDelete();
            $table->string('area', 40);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->date('retention_until'); // Fristende (danach löschbar)
            $table->string('reason', 300);
            $table->string('status', 16)->default('pending'); // pending|approved|rejected|purged
            $table->foreignId('decided_by')->nullable()
                ->constrained('users', indexName: 'rp_decided_by_fk')
                ->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'area', 'subject_type', 'subject_id'], 'rp_org_subject_uq');
            $table->index(['organization_id', 'status'], 'rp_org_status_idx');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('legal_region', 2)->default('DE')->after('timezone');
        });
    }

    public function down(): void {
        Schema::dropIfExists('retention_proposals');
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('legal_region');
        });
    }
};
