<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120002_add_expense_id_to_invoice_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('expense_id')->nullable()->after('time_entry_id')
                ->constrained('expenses')->nullOnDelete();
            $table->index('expense_id');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropForeign(['expense_id']);
            $table->dropIndex(['expense_id']);
            $table->dropColumn('expense_id');
        });
    }
};
