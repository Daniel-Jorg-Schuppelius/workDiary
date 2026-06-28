<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_26_130000_create_pricing_margin_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margenregeln für Verkaufspreisvorschläge (Feature 050, MVP-095). Eine Regel
 * greift optional eingeschränkt auf einen Lieferanten und/oder eine Warengruppe
 * (Kategorie des Katalogartikels). Aus Zielmarge ODER Aufschlag entsteht ein
 * Vorschlag; Mindestmarge und Mindestverkaufspreis sind Leitplanken, die
 * Rundung erfolgt immer aufwärts. Kurze, explizite FK-Namen (MySQL-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('pricing_margin_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pmr_org_fk')->cascadeOnDelete();
            $table->string('name', 191);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers', indexName: 'pmr_sup_fk')->cascadeOnDelete();
            $table->string('category', 191)->nullable();
            $table->decimal('markup_percent', 8, 3)->nullable();   // Aufschlag in %
            $table->decimal('target_margin', 8, 3)->nullable();    // Zielmarge in %
            $table->decimal('min_margin', 8, 3)->nullable();       // Mindestmarge in %
            $table->decimal('min_sale_price', 18, 4)->nullable();  // Mindestverkaufspreis
            $table->string('rounding', 12)->default('none');       // PriceRounding
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'supplier_id'], 'pmr_org_sup_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('pricing_margin_rules');
    }
};
