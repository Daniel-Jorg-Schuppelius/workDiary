<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_120000_add_register_number_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1686: Registerangabe des Kunden (Handelsregister, Freitext wie die
 * eigene Angabe der Organisation). Die E-Rechnung führt sie als
 * Registerkennung des Käufers (BT-47); bei § 13b ohne USt-IdNr. ersetzt sie
 * die USt-IdNr. (BR-AE-02).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('customers', 'register_number')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('register_number', 200)->nullable()->after('tax_number');
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('customers', 'register_number')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('register_number');
        });
    }
};
