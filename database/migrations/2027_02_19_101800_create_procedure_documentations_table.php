<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_101800_create_procedure_documentations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GoBD-Verfahrensdokumentation (Feature 134, MVP-699; Vollscan 2026-08-23,
 * H17): je Org versionierte Dokumente — Freitext-Pflichtteile des Betreibers
 * plus eingefrorener JSON-Snapshot des generierten Systemteils und
 * SHA-256 des veröffentlichten PDFs.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_documentations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Laufende Version je Org (v1, v2, …).
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft'); // draft|published
            // Freitext-Pflichtteile (GoBD Rz. 151 ff.).
            $table->text('general_description')->nullable();
            $table->text('user_documentation')->nullable();
            $table->text('technical_documentation')->nullable();
            $table->text('operational_documentation')->nullable();
            $table->text('change_history')->nullable();
            // Ab Veröffentlichung: Snapshot des generierten Teils + Nachweise.
            $table->longText('snapshot')->nullable();
            $table->string('snapshot_sha256', 64)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'version'], 'procedure_docs_org_version_uq');
            $table->index(['organization_id', 'status'], 'procedure_docs_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_documentations');
    }
};
