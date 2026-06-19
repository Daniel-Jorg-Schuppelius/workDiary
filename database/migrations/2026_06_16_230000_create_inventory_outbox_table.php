<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_230000_create_inventory_outbox_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistierte Outbox für die externe Bestandsführung (Feature 048, MVP-072).
 * Eine lokal gebuchte Bewegung und ihr Outbox-Eintrag entstehen transaktional;
 * die externe Bestätigung läuft asynchron und idempotent (eindeutiger
 * `idempotency_key` je Organisation). Endgültige Fehlschläge werden für eine
 * fachliche Kompensation markiert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('plugin_id', 64)->nullable();
            $table->string('operation', 48);
            $table->json('payload');
            $table->string('idempotency_key', 128);
            $table->string('status', 24)->default('pending'); // OutboxStatus
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'inventory_outbox_idem_uq');
            $table->index(['organization_id', 'status'], 'inventory_outbox_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventory_outbox');
    }
};
