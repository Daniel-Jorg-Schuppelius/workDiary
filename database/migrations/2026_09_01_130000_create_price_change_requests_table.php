<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_01_130000_create_price_change_requests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mehrstufiger Freigabeflow für Verkaufspreisübernahmen (Feature 050,
 * MVP-095): Im Vier-Augen-Modus wird eine Preisübernahme beantragt und von
 * einer zweiten Person genehmigt oder abgelehnt. Der Antrag friert den
 * beantragten Vorschlag als Snapshot ein; weicht der zur Genehmigung neu
 * berechnete Vorschlag ab, verfällt der Antrag (expired). Kurze, explizite
 * Index-/FK-Namen (64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('price_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pcr_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_catalog_item_id')->constrained('supplier_catalog_items', indexName: 'pcr_item_fk')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles', indexName: 'pcr_art_fk')->cascadeOnDelete();
            $table->foreignId('pricing_margin_rule_id')->nullable()->constrained('pricing_margin_rules', indexName: 'pcr_rule_fk')->nullOnDelete();
            $table->decimal('purchase_price_snapshot', 18, 4);
            $table->decimal('suggested_price', 18, 4);
            $table->decimal('margin_snapshot', 8, 3);
            $table->string('status', 16)->default('requested'); // requested / approved / rejected / expired
            $table->foreignId('requested_by')->constrained('users', indexName: 'pcr_req_fk')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'pcr_dec_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'pcr_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('price_change_requests');
    }
};
