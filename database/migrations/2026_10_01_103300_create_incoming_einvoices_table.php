<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_103300_create_incoming_einvoices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 066, MVP-165/167: Prüfbereich für eingehende E-Rechnungen —
 * inhaltsbasierter SHA-256 (Dedup je Org), Herkunft/Empfangszeit,
 * Freigabe-Workflow (received → approved|rejected|question →
 * payment_released). Das Original liegt unverändert im DMS (Document).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('incoming_einvoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'ine_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('document_id')
                ->constrained('documents', indexName: 'ine_document_fk')
                ->cascadeOnDelete();
            $table->char('sha256', 64);
            $table->string('source', 12)->default('upload'); // upload|mail|api|peppol
            $table->timestamp('received_at');
            $table->string('status', 20)->default('received'); // received|approved|rejected|question|payment_released
            $table->foreignId('decided_by')->nullable()
                ->constrained('users', indexName: 'ine_decided_by_fk')
                ->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->json('summary')->nullable(); // Nummer/Verkäufer/Brutto/Fälligkeit
            $table->timestamps();

            $table->unique(['organization_id', 'sha256'], 'ine_org_hash_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('incoming_einvoices');
    }
};
