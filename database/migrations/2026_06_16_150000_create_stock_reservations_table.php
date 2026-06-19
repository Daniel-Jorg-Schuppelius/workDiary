<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_150000_create_stock_reservations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestandsreservierungen als eigene Entität (Feature 048, MVP-068). Ergänzt den
 * Reserved-Zustand des Journals um Lebenszyklus, Priorität und fachliche Quelle
 * (z. B. Fertigungsauftrag). `quantity` ist die reservierte Gesamtmenge,
 * `consumed_qty` der bereits in Verbrauch überführte Anteil; offen = Differenz.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            $table->decimal('quantity', 18, 4);
            $table->decimal('consumed_qty', 18, 4)->default(0);
            $table->string('ownership_type', 12)->default('own');
            $table->string('owner_ref', 64)->nullable();
            $table->string('status', 12)->default('active'); // ReservationStatus
            $table->unsignedInteger('priority')->default(100);

            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamp('reserved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['article_variant_id', 'warehouse_id', 'status'], 'stock_resv_bucket_idx');
            $table->index(['source_type', 'source_id'], 'stock_resv_source_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_reservations');
    }
};
