<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_11_100100_add_customer_approval_to_print_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1076: Kunden-Druckfreigabe getrennt von der internen Freigabe. Die
 * Anforderung friert Dateiversion, Prüfsumme und Parameter ein; die
 * Entscheidung hält Portalperson, Zeitpunkt und Hash fest.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('print_orders', function (Blueprint $table): void {
            $table->boolean('is_customer_approval_required')->default(false);
            $table->timestamp('customer_approval_requested_at')->nullable();
            $table->json('customer_approval_request')->nullable();
            $table->timestamp('customer_approved_at')->nullable();
            $table->foreignId('customer_approval_user_id')->nullable()->constrained('users', indexName: 'po_customer_approval_user_fk')->nullOnDelete();
            $table->string('customer_approved_file_hash', 64)->nullable();
            $table->timestamp('customer_declined_at')->nullable();
            $table->text('customer_decline_reason')->nullable();
        });
    }

    public function down(): void {
        Schema::table('print_orders', function (Blueprint $table): void {
            $table->dropForeign('po_customer_approval_user_fk');
            $table->dropColumn([
                'is_customer_approval_required',
                'customer_approval_requested_at',
                'customer_approval_request',
                'customer_approved_at',
                'customer_approval_user_id',
                'customer_approved_file_hash',
                'customer_declined_at',
                'customer_decline_reason',
            ]);
        });
    }
};
