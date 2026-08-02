<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_20_100000_create_billing_transfer_positions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eingefrorene Positionen einer Übergabe (MVP-487): beim Bestätigen aus
 * Taktung, Preisfindung und Standardleistung erzeugt, danach prüf- und
 * textbearbeitbar. Die Ziele senden genau diese Zeilen — „was die Vorschau
 * zeigt, geht raus". Die Quell-Zuordnung bleibt in billing_transfer_items
 * (Nachweis); hier steht die Rechnungssicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('billing_transfer_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('billing_transfer_id')->constrained('billing_transfers')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('source_kind', 16);                    // time|material
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('kind', 32)->nullable();               // TimeEntryKind
            $table->json('source_ids')->nullable();               // gebündelte Quellen (Zeiten/Verwendungen)
            $table->unsignedBigInteger('primary_source_id')->nullable();

            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->timestamp('ai_assisted_at')->nullable();      // Transparenz (Feature 084)

            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('unit_name', 32)->nullable();
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('amount', 12, 2)->default(0);

            $table->string('article_id', 64)->nullable();         // Fremd-Artikel der Standardleistung
            $table->string('service_source', 32)->nullable();     // project_rule|organization
            $table->string('price_source', 32)->nullable();       // snapshot|entry|customer|service|org_default|none
            $table->date('service_from')->nullable();
            $table->date('service_to')->nullable();

            $table->timestamps();

            $table->index(['billing_transfer_id', 'position'], 'btp_transfer_position_idx');
            $table->index(['organization_id', 'billing_transfer_id'], 'btp_org_transfer_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('billing_transfer_positions');
    }
};
