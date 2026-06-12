<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_100000_create_isms_software_products_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisationsbezogenes Softwareinventar (Feature 044, MVP 1, Ebene 1):
 * eingesetzte Softwareprodukte mit führender Version, Verantwortlichem,
 * Support-Status und End-of-Life-Datum. Die konkreten Einsatzorte liegen
 * in isms_software_installations (Ebene 1, Installationen); die
 * produktbezogene WorkDiary-SBOM (Ebene 2) ist davon getrennt
 * (SbomGenerator / sbom:generate).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_software_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('vendor', 120)->nullable();
            // „Führende" Version des Produkts — Installationen können abweichen.
            $table->string('product_version', 64)->nullable();
            // os|application|service|library|other (App\Enums\Isms\SoftwareCategory).
            $table->string('category', 16)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            // supported|extendedSupport|endOfLife|unknown (App\Enums\Isms\SupportStatus).
            $table->string('support_status', 24)->default('unknown');
            $table->date('eol_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->index(['organization_id', 'name'], 'isms_sw_org_name_idx');
            $table->index(['organization_id', 'support_status'], 'isms_sw_org_status_idx');
            $table->index(['organization_id', 'eol_on'], 'isms_sw_org_eol_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_software_products');
    }
};
