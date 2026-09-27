<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_340000_create_incoming_invoice_retentions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-953: Sicherheits- und Gewährleistungseinbehalte an Eingangsrechnungen. */
return new class extends Migration {
    public function up(): void {
        Schema::create('incoming_invoice_retentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'incoming_ret_org_fk')->cascadeOnDelete();
            $table->foreignId('incoming_einvoice_id')->constrained('incoming_einvoices', indexName: 'incoming_ret_invoice_fk')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('due_on')->nullable();
            $table->string('status', 16);
            $table->date('released_on')->nullable();
            $table->foreignId('releaser_user_id')->nullable()->constrained('users', indexName: 'incoming_ret_releaser_fk')->nullOnDelete();
            $table->foreignId('paid_in_run_id')->nullable()->constrained('payment_runs', indexName: 'incoming_ret_run_fk')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'incoming_ret_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_on'], 'incoming_ret_status_due_idx');
        });

        Schema::table('payment_run_items', function (Blueprint $table): void {
            $table->foreignId('incoming_invoice_retention_id')->nullable()->after('incoming_einvoice_id')
                ->constrained('incoming_invoice_retentions', indexName: 'payment_items_retention_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('payment_run_items', function (Blueprint $table): void {
            $table->dropForeign('payment_items_retention_fk');
            $table->dropColumn('incoming_invoice_retention_id');
        });
        Schema::dropIfExists('incoming_invoice_retentions');
    }
};
