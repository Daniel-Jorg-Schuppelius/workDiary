<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_210000_create_boq_call_offs_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-931: Zeitvertragsarbeiten — Rahmen-LV mit Abrufen. */
return new class extends Migration {
    public function up(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->boolean('is_framework')->default(false)->after('status');
        });

        Schema::create('boq_call_offs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boq_call_offs_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boq_call_offs_boq_fk')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 200);
            $table->date('ordered_on')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 16);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices', indexName: 'boq_call_offs_invoice_fk')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'boq_call_offs_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'boq_call_offs_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bill_of_quantity_id', 'number'], 'boq_call_offs_boq_number_uq');
        });

        Schema::create('boq_call_off_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boq_call_off_items_org_fk')->cascadeOnDelete();
            $table->foreignId('boq_call_off_id')->constrained('boq_call_offs', indexName: 'boq_call_off_items_call_off_fk')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items', indexName: 'boq_call_off_items_item_fk')->cascadeOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->timestamps();

            $table->unique(['boq_call_off_id', 'boq_item_id'], 'boq_call_off_items_call_off_item_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_call_off_items');
        Schema::dropIfExists('boq_call_offs');
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->dropColumn('is_framework');
        });
    }
};
