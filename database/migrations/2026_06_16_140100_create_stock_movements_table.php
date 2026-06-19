<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_140100_create_stock_movements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only Lagerbewegungsjournal (Feature 048, MVP-067). Bestände werden
 * NICHT direkt überschrieben, sondern aus diesem unveränderlichen Journal
 * abgeleitet (Summe der signierten Mengen je Bucket variant/warehouse/state/
 * ownership). `qty_base` ist die signierte Menge in der Basiseinheit; der
 * Originalwert/-einheit bleibt als Snapshot erhalten. `idempotency_key`
 * verhindert Doppelbuchungen (z. B. bei externem Retry).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            $table->string('stock_state', 12);       // StockState
            $table->string('ownership_type', 12)->default('own'); // OwnershipType
            $table->string('owner_ref', 64)->nullable(); // z. B. Kunden-/Projekt-Referenz
            $table->string('movement_type', 24);      // StockMovementType

            $table->decimal('qty_base', 18, 4);       // signierte Menge in Basiseinheit
            $table->decimal('original_qty', 18, 4)->nullable();
            $table->string('original_unit', 20)->nullable();

            $table->timestamp('occurred_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Fachliche Quelle (z. B. Fertigungsauftrag) als optionaler Morph.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('idempotency_key', 100)->nullable();

            // Kostensnapshot (MVP-070 nutzt ihn; hier optional vorgesehen).
            $table->decimal('cost_unit', 18, 4)->nullable();
            $table->decimal('cost_total', 18, 4)->nullable();
            $table->string('currency', 3)->nullable();

            $table->timestamps();

            $table->index('organization_id');
            $table->index(['article_variant_id', 'warehouse_id', 'stock_state'], 'stock_mov_bucket_idx');
            $table->index(['source_type', 'source_id'], 'stock_mov_source_idx');
            $table->unique(['organization_id', 'idempotency_key'], 'stock_mov_idem_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_movements');
    }
};
