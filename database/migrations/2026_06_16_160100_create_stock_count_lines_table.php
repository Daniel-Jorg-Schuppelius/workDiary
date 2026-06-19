<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_160100_create_stock_count_lines_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zählzeilen einer Inventur (Feature 048, MVP-069): je Bucket (Variante,
 * Bestandszustand, Eigentumsart) der eingefrorene Sollbestand (`book_qty`) und
 * die erfasste Zählmenge (`counted_qty`). Differenz = counted − book. Beim
 * Freigeben wird die Differenz als eigene, auditierte Korrekturbuchung gebucht.
 * Mandantengrenze transitiv über stock_counts.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->string('stock_state', 12)->default('physical');
            $table->string('ownership_type', 12)->default('own');
            $table->decimal('book_qty', 18, 4)->default(0);
            $table->decimal('counted_qty', 18, 4)->nullable();
            $table->boolean('applied')->default(false);
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('stock_count_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_count_lines');
    }
};
