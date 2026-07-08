<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101200_create_isms_audit_programs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mehrjähriges Auditprogramm (Nachtrag 044d): bündelt Einzel-Audits eines
 * Geltungsbereichs über einen Zertifizierungszyklus (z. B. 3 Jahre ISO
 * 27001: Erst-/Überwachungs-/Re-Zertifizierungsaudit). Nachweis läuft über
 * die verknüpften Audits (Feststellungen/Maßnahmen/Auditpakete).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_audit_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'iap_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('isms_scope_id')
                ->constrained('isms_scopes', indexName: 'iap_scope_fk')
                ->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('norm', 64)->nullable();
            $table->string('edition', 16)->nullable();
            $table->unsignedTinyInteger('cycle_years')->default(3);
            $table->date('starts_on');
            $table->string('status', 16)->default('active'); // active|completed|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'iap_org_status_idx');
        });

        Schema::table('isms_audits', function (Blueprint $table): void {
            $table->foreignId('isms_audit_program_id')->nullable()
                ->after('isms_scope_id')
                ->constrained('isms_audit_programs', indexName: 'ia_program_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('isms_audits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('isms_audit_program_id');
        });
        Schema::dropIfExists('isms_audit_programs');
    }
};
