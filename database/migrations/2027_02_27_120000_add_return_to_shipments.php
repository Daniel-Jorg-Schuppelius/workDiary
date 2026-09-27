<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_120000_add_return_to_shipments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-917: Retourenlabel für eine RMA. */
return new class extends Migration {
    public function up(): void {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->boolean('is_return')->default(false)->after('status');
            $table->foreignId('claim_rma_return_id')->nullable()->after('stock_delivery_id')
                ->constrained('claim_rma_returns', indexName: 'shipments_claim_rma_return_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign('shipments_claim_rma_return_fk');
            $table->dropColumn(['is_return', 'claim_rma_return_id']);
        });
    }
};
