<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_28_100000_create_supplier_credentials_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subunternehmer-Pflichtnachweise (Feature 117, MVP-606).
 *
 * Der Typenkatalog liegt wie die Prüfmittel-Profile als Katalogdaten mit
 * `organization_id = NULL` vor (Muster AssetComplianceProfile): Eine neue
 * Nachweisart ist dann Datenpflege statt Release. Organisationen können eigene
 * Typen ergänzen, ohne den Katalog zu verändern.
 *
 * Der gefährliche Fall ist nicht das fehlende, sondern das **abgelaufene**
 * Dokument — deshalb ist `valid_until` Pflicht, sobald der Typ befristet ist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_credential_types', function (Blueprint $table): void {
            $table->id();
            // NULL = Katalog (installationsweit), sonst org-eigener Typ
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name', 191);
            $table->unsignedSmallInteger('default_validity_months')->nullable();
            $table->unsignedSmallInteger('warn_days_before')->default(30);
            // warn | block — Sperren ist eine bewusste Entscheidung des Betriebs
            $table->string('blocking_mode', 16)->default('warn');
            $table->boolean('is_required_default')->default(false);
            $table->string('description', 500)->nullable();
            $table->string('frame_version', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'supp_cred_type_org_code_unq');
        });

        Schema::create('supplier_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_credential_type_id')->constrained('supplier_credential_types')->cascadeOnDelete();
            $table->string('issuer', 191)->nullable();
            $table->string('reference', 64)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('checked_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'supplier_id'], 'supp_cred_org_supplier_idx');
            $table->index(['organization_id', 'valid_until'], 'supp_cred_org_valid_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_credentials');
        Schema::dropIfExists('supplier_credential_types');
    }
};
