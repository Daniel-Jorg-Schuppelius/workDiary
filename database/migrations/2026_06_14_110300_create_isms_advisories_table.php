<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_110300_create_isms_advisories_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importierte Advisories (Feature 044, MVP 2): Nachweis-Ablage des
 * Original-Advisories (CSAF/VEX-JSON) je Import — Datei im local-Disk plus
 * SHA-256-Hash. document_id_ref hält die CSAF-Tracking-ID (document.tracking.id),
 * vuln_count die Zahl der erzeugten/aktualisierten Schwachstellen. Die
 * Re-Import-Idempotenz nutzt (organization_id, file_hash): identische Dateien
 * werden nicht doppelt abgelegt (AdvisoryImportService).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_advisories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 250);
            // csaf|vex (App\Enums\Isms\AdvisoryFormat).
            $table->string('format', 8);
            // CSAF document.tracking.id bzw. VEX-Dokument-ID (Freitext).
            $table->string('document_id_ref', 250)->nullable();
            $table->string('file_path', 255);
            $table->string('file_hash', 64);
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('vuln_count')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'file_hash'], 'isms_adv_org_hash_uq');
            $table->index(['organization_id', 'format'], 'isms_adv_org_format_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_advisories');
    }
};
