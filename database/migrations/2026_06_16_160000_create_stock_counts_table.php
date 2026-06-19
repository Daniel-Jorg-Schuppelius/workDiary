<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_160000_create_stock_counts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stichtagsbezogene Inventur (Feature 048, MVP-069): `counted_at` ist der
 * Zählzeitpunkt, zu dem der Sollbestand je Bucket eingefroren wird. Bewegungen
 * nach dem Zählzeitpunkt laufen separat weiter; die Differenz bezieht sich auf
 * den eingefrorenen Sollbestand. Zählung, Prüfung und Freigabe können
 * verschiedenen Personen zugewiesen sein.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('status', 12)->default('counting'); // StockCountStatus
            $table->timestamp('counted_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['warehouse_id', 'status'], 'stock_counts_wh_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_counts');
    }
};
