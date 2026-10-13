<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_20_110000_create_delivery_notes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Lieferschein als eigener Beleg (Phase 145, MVP-1163): Nummernkreis,
 * Positionen ohne Preise, Vorgänger AB oder Fertigungsauftrag. Bestehende
 * Lagerauslieferungen bekommen ihren Lieferschein mit derselben ID und der
 * bisherigen Nummer `LS-<id>` — Versandnachweise (`document_dispatches`)
 * verweisen über diese ID.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'dln_org_fk')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'dln_customer_fk')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'dln_project_fk')->nullOnDelete();
            $table->foreignId('order_confirmation_id')->nullable()->constrained('order_confirmations', indexName: 'dln_confirmation_fk')->nullOnDelete();
            $table->foreignId('manufacturing_order_id')->nullable()->constrained('manufacturing_orders', indexName: 'dln_mfg_order_fk')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('draft');
            $table->date('delivered_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'dln_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'dln_org_number_unique');
        });

        Schema::create('delivery_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'dli_org_fk')->cascadeOnDelete();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes', indexName: 'dli_note_fk')->cascadeOnDelete();
            $table->foreignId('order_confirmation_item_id')->nullable()->constrained('order_confirmation_items', indexName: 'dli_confirmation_item_fk')->nullOnDelete();
            $table->foreignId('stock_delivery_id')->nullable()->unique('dli_stock_delivery_unique')->constrained('stock_deliveries', indexName: 'dli_stock_delivery_fk')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles', indexName: 'dli_article_fk')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('line_kind', 20)->default('item');
            $table->string('sku', 100)->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 12, 4)->default(0);
            $table->string('unit', 20)->nullable();
            $table->timestamps();
        });

        $this->adoptStockDeliveries();
    }

    public function down(): void {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }

    private function adoptStockDeliveries(): void {
        DB::table('stock_deliveries')
            ->join('organizations', 'organizations.id', '=', 'stock_deliveries.organization_id')
            ->select('stock_deliveries.*')
            ->orderBy('stock_deliveries.id')
            ->chunk(500, function ($rows): void {
                $notes = [];
                $items = [];
                foreach ($rows as $row) {
                    $notes[] = [
                        'id' => $row->id,
                        'organization_id' => $row->organization_id,
                        'customer_id' => $row->customer_id,
                        'manufacturing_order_id' => $row->manufacturing_order_id,
                        'number' => 'LS-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
                        'status' => 'delivered',
                        'delivered_on' => $row->delivered_at !== null ? substr((string) $row->delivered_at, 0, 10) : null,
                        'created_by' => $row->created_by,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                    $items[] = [
                        'organization_id' => $row->organization_id,
                        'delivery_note_id' => $row->id,
                        'stock_delivery_id' => $row->id,
                        'position' => 1,
                        'line_kind' => 'item',
                        'sku' => $row->sku_snapshot !== null ? mb_substr((string) $row->sku_snapshot, 0, 100) : null,
                        'description' => mb_substr((string) ($row->name_snapshot ?? ''), 0, 500),
                        'quantity' => $row->quantity ?? 0,
                        'unit' => $row->unit !== null ? mb_substr((string) $row->unit, 0, 20) : null,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }
                DB::table('delivery_notes')->insert($notes);
                DB::table('delivery_note_items')->insert($items);
            });

        // PostgreSQL führt die Sequenz bei expliziten IDs nicht mit.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('delivery_notes', 'id'), COALESCE((SELECT MAX(id) FROM delivery_notes), 0) + 1, false)");
        }
    }
};
