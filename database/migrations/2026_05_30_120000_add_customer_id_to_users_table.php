<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_30_120000_add_customer_id_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            // Verknuepft einen Portal-User mit dem Kunden, dessen Daten er
            // einsehen darf. Nur fuer Rolle `kunde` gesetzt; interne Nutzer
            // bleiben auf NULL. Wird vom CustomerUserProvider als
            // Pflicht-Kriterium gegen den `customer`-Guard verwendet und vom
            // LegacyUserProvider als Ausschluss-Kriterium gegen den `web`-
            // Guard, damit Portal-Accounts nie auf interne Routen kommen.
            $table->foreignId('customer_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->index(['customer_id']);
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
