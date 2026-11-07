<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_03_110000_add_einvoice_recipient_profile_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Empfängerprofil und Versandweg der E-Rechnung je Kunde (Feature 196, MVP-1486/1488); null = Vorgabe der Organisation. */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $t): void {
            $t->string('einvoice_recipient_profile', 32)->nullable()->after('delivery_format');
            $t->boolean('einvoice_attach_activity_report')->nullable()->after('einvoice_recipient_profile');
            $t->unsignedTinyInteger('einvoice_attachment_limit_mb')->nullable()->after('einvoice_attach_activity_report');
            // Versandweg extern ausgestellter Rechnungen (Nachtrag 07.11.2026): provider oder workdiary.
            $t->string('einvoice_dispatch_route', 16)->nullable()->after('einvoice_attachment_limit_mb');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $t): void {
            $t->dropColumn(['einvoice_recipient_profile', 'einvoice_attach_activity_report', 'einvoice_attachment_limit_mb', 'einvoice_dispatch_route']);
        });
    }
};
