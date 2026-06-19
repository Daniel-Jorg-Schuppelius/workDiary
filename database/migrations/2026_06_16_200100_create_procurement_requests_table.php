<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_200100_create_procurement_requests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beschaffungsbedarf / offener Punkt aus Fehlmengen (Feature 048,
 * Fehlmaterialprozess). Der erste MVP erzeugt aus Fehlmengen einen offenen
 * Bedarf; vollständige Bestellungen und Bestellvorschläge bleiben Folgeausbau.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('procurement_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('status', 12)->default('open'); // ProcurementStatus
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['status', 'article_id'], 'procurement_status_article_idx');
            $table->index(['source_type', 'source_id'], 'procurement_source_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procurement_requests');
    }
};
