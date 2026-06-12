<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_120100_create_isms_certificates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zertifikatsregister (Feature 046, Inkrement B): hinterlegte Zertifikate
 * je Konformitätsstatus (isms_norm_statuses). Pflichtfelder gemäß 046:
 * zertifizierte Organisation, Geltungsbereich laut Zertifikat,
 * Zertifizierungsstelle, Zertifikatsnummer, Ausstellungsdatum und
 * Gültigkeitszeitraum (Norm + Ausgabe ergeben sich aus dem NormStatus);
 * Überwachungstermine optional. Die Zertifikatsdatei liegt im
 * Dokumentenmodul (document_id, nullOnDelete — das Zertifikat bleibt
 * als Registereintrag erhalten).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_norm_status_id')->constrained('isms_norm_statuses')->cascadeOnDelete();
            $table->string('certified_organization', 180);
            $table->text('scope_description');
            $table->string('certification_body', 180);
            $table->string('certificate_no', 120);
            $table->date('issued_on');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->date('surveillance_audit_1_on')->nullable();
            $table->date('surveillance_audit_2_on')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->index(['organization_id', 'valid_until'], 'isms_cert_org_valid_idx');
            $table->index(['isms_norm_status_id', 'valid_until'], 'isms_cert_status_valid_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_certificates');
    }
};
