<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_100200_create_jtl_stock_snapshots_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spiegelbestand der führenden JTL-Wawi (Feature 078, MVP-320): Anzeige-
 * und Cache-Schicht mit sichtbarem Datenalter (`fetched_at`). Der Snapshot
 * ist NIE Buchungsgrundlage — der Provider liest bei abgelaufener TTL live.
 * Mengen als decimal(14,4) analog `stock_movements.qty_base`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('jtl_stock_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jtlss_org_fk')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants', indexName: 'jtlss_var_fk')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses', indexName: 'jtlss_wh_fk')->cascadeOnDelete();
            $table->decimal('quantity_total', 14, 4)->default(0);
            $table->decimal('quantity_available', 14, 4)->default(0);
            $table->decimal('quantity_reserved', 14, 4)->default(0);
            $table->decimal('quantity_blocked', 14, 4)->default(0);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['organization_id', 'article_variant_id', 'warehouse_id'], 'jtlss_org_var_wh_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('jtl_stock_snapshots');
    }
};
