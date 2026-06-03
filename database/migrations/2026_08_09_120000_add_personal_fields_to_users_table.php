<?php
/*
 * Created on   : Tue Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_09_120000_add_personal_fields_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert die Mitarbeitererfassung um strukturierte Namens- und
 * Kommunikationsdaten. `name` bleibt der durchgängig verwendete Anzeigename;
 * Vor-/Nach-/Zwischennamen werden zusätzlich strukturiert abgelegt, damit
 * Mitarbeiter sauber (z. B. für Exporte/Briefe) erfasst werden können.
 *
 * Adress- und Bankdaten werden NICHT hier abgelegt, sondern – konsistent mit
 * Customer/Supplier – über die polymorphen Tabellen contact_addresses /
 * contact_bank_accounts (addressable/accountable = App\Models\User).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 128)->nullable()->after('name');
            $table->string('middle_names', 128)->nullable()->after('first_name');
            $table->string('last_name', 128)->nullable()->after('middle_names');
            $table->string('phone', 64)->nullable()->after('last_name');
            $table->string('mobile', 64)->nullable()->after('phone');
            $table->string('fax', 64)->nullable()->after('mobile');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'middle_names', 'last_name', 'phone', 'mobile', 'fax']);
        });
    }
};
