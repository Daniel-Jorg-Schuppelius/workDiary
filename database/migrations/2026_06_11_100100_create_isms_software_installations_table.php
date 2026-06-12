<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_100100_create_isms_software_installations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installationen je Softwareprodukt (Feature 044, MVP 1): WO läuft welche
 * Version? asset_ref ist im MVP bewusst Freitext (Server/Gerät/Dienst,
 * analog isms_risks.asset_ref) — KEIN FK auf assets; das Asset-Register
 * folgt in MVP 2+.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_software_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('isms_software_product_id')
                ->constrained('isms_software_products', indexName: 'isms_swi_product_fk')
                ->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Tatsächlich installierte Version (kann von der führenden abweichen).
            $table->string('installed_version', 64)->nullable();
            // Freitext-Bezug: Server/Gerät/Dienst (kein Asset-FK im MVP).
            $table->string('asset_ref', 180)->nullable();
            $table->string('location', 180)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->index(['organization_id', 'isms_software_product_id'], 'isms_swi_org_product_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_software_installations');
    }
};
