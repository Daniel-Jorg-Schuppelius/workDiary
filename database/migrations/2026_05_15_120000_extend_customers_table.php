<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_15_120000_extend_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert customers um:
 *  - strukturierte Adresse (street/zip/city) zusätzlich zum Freitext-Feld 'address'
 *  - mehrere Kontaktpersonen als JSON
 *  - automatischer Suchindex auf Name
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('address_street', 255)->nullable()->after('address');
            $table->string('address_zip', 32)->nullable()->after('address_street');
            $table->string('address_city', 128)->nullable()->after('address_zip');
            $table->json('contact_persons')->nullable()->after('contact_name');

            $table->index('name', 'customers_name_idx');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_name_idx');
            $table->dropColumn(['address_street', 'address_zip', 'address_city', 'contact_persons']);
        });
    }
};
