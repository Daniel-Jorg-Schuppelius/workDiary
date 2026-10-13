<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_20_100000_create_order_confirmations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auftragsbestätigung als eigener Beleg (Phase 145, MVP-1162): Nummernkreis,
 * Positionen nach dem Belegpositionsvertrag, Bezug zum Angebot.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('order_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ocf_org_fk')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers', indexName: 'ocf_customer_fk')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'ocf_project_fk')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes', indexName: 'ocf_quote_fk')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('draft');
            $table->date('confirmed_on')->nullable();
            $table->text('note')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ocf_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'ocf_org_number_unique');
        });

        Schema::create('order_confirmation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'oci_org_fk')->cascadeOnDelete();
            $table->foreignId('order_confirmation_id')->constrained('order_confirmations', indexName: 'oci_confirmation_fk')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles', indexName: 'oci_article_fk')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('line_kind', 20)->default('item');
            $table->string('description', 500);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('tax_category', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('order_confirmation_items');
        Schema::dropIfExists('order_confirmations');
    }
};
