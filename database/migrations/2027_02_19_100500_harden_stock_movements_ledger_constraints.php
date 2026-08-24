<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Vollscan 2026-08-23, F5: stock_movements ist der append-only Bestands-
 * Ledger — CASCADE von article_variants/warehouses konnte Historie löschen,
 * organization_id nullable+SET NULL machte Zeilen durch den OrganizationScope
 * unsichtbar. RESTRICT + NOT NULL; bricht ab, wenn NULL-Zeilen existieren
 * (Betreiber muss zuordnen — raten wäre eine Ledger-Fälschung). Nur MySQL.
 */
return new class extends Migration {
    public function up(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $orphans = DB::table('stock_movements')->whereNull('organization_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "stock_movements: {$orphans} Zeilen ohne organization_id — vor dieser Migration zuordnen (Ledger wird NOT NULL).",
            );
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign('stock_movements_article_variant_id_foreign');
            $table->dropForeign('stock_movements_warehouse_id_foreign');
            $table->dropForeign('stock_movements_organization_id_foreign');
        });
        DB::statement('ALTER TABLE stock_movements MODIFY organization_id BIGINT UNSIGNED NOT NULL');
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreign('article_variant_id', 'stock_movements_article_variant_id_foreign')->references('id')->on('article_variants')->restrictOnDelete();
            $table->foreign('warehouse_id', 'stock_movements_warehouse_id_foreign')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('organization_id', 'stock_movements_organization_id_foreign')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign('stock_movements_article_variant_id_foreign');
            $table->dropForeign('stock_movements_warehouse_id_foreign');
            $table->dropForeign('stock_movements_organization_id_foreign');
        });
        DB::statement('ALTER TABLE stock_movements MODIFY organization_id BIGINT UNSIGNED NULL');
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreign('article_variant_id', 'stock_movements_article_variant_id_foreign')->references('id')->on('article_variants')->cascadeOnDelete();
            $table->foreign('warehouse_id', 'stock_movements_warehouse_id_foreign')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('organization_id', 'stock_movements_organization_id_foreign')->references('id')->on('organizations')->nullOnDelete();
        });
    }
};
