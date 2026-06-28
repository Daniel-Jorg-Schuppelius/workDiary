<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_26_140000_create_pricing_change_alerts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalkulationswarnungen bei Einkaufspreisänderungen (Feature 050, MVP-094).
 * Steigt der Lieferanten-EK eines verknüpften Artikels so, dass der hinterlegte
 * Verkaufspreis unter die Mindestmarge fällt, entsteht eine offene Warnung.
 * Historische Vorgänge werden nicht verändert. Kurze, explizite FK-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('pricing_change_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pca_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_catalog_item_id')->constrained('supplier_catalog_items', indexName: 'pca_item_fk')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles', indexName: 'pca_art_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers', indexName: 'pca_sup_fk')->nullOnDelete();
            $table->decimal('old_purchase_price', 18, 4)->nullable();
            $table->decimal('new_purchase_price', 18, 4);
            $table->decimal('sale_price', 18, 4);
            $table->decimal('new_margin', 8, 3);            // resultierende Marge in %
            $table->decimal('min_margin', 8, 3)->nullable(); // greifende Mindestmarge in %
            $table->string('status', 16)->default('open');   // open / acknowledged
            $table->foreignId('acknowledged_by')->nullable()->constrained('users', indexName: 'pca_ack_fk')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'pca_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('pricing_change_alerts');
    }
};
