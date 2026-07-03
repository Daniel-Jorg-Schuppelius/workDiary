<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_200200_create_billing_transfer_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quellnachweis je Übergabeposition (Feature 045): morphte Referenz auf
 * TimeEntry|MaterialUsage plus Mengen-/Betrags-Snapshot zum Übergabezeitpunkt.
 * Kind-Tabelle ohne eigene organization_id — Mandantengrenze transitiv über
 * billing_transfers.organization_id (siehe ../WorkDiary-Architecture/security/tenant-audit-2026.md).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('billing_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_transfer_id')->constrained('billing_transfers')->cascadeOnDelete();
            $table->string('source_type');                      // TimeEntry|MaterialUsage (FQCN/Morph)
            $table->unsignedBigInteger('source_id');
            $table->decimal('amount', 12, 2)->nullable();       // Betrags-Snapshot
            $table->decimal('quantity', 10, 2)->nullable();     // Mengen-Snapshot (Stunden/Stück)
            $table->timestamp('created_at')->nullable();

            $table->unique(['billing_transfer_id', 'source_type', 'source_id'], 'bti_transfer_source_unique');
            $table->index(['source_type', 'source_id'], 'bti_source_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('billing_transfer_items');
    }
};
