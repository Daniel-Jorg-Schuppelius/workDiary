<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_150100_create_isms_audit_package_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitlich begrenzte Prüfer-Download-Tokens für finalisierte Auditpakete
 * (Feature 046, Inkrement E / 044 „optionaler zeitlich begrenzter,
 * lesender Prüferzugang").
 *
 * Der Klartext-Token wird NICHT gespeichert; persistiert wird nur der
 * SHA-256-Hash (Muster ProtocolSignatureToken / Laravel Password-Reset).
 * Kind-Tabelle des tenant-gebundenen Auditpakets — Mandantengrenze
 * transitiv über isms_audit_packages.organization_id (Allow-List im
 * TenantTraitCoverageTest, Begründung in docs/security/tenant-audit-2026.md).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_audit_package_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('isms_audit_package_id')->constrained('isms_audit_packages')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique('isms_pkg_token_hash_uq');
            $table->string('label', 120); // z. B. „Auditor Müller"
            $table->timestamp('expires_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_audit_package_tokens');
    }
};
