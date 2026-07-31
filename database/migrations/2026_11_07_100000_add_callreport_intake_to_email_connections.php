<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_07_100000_add_callreport_intake_to_email_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FritzBox-Plugin: Kennzeichnung eines Postfachs für den automatischen Abgriff
 * der monatlichen FRITZ!Box-Telefonberichte — CSV-Anhänge laufen in den
 * Anruflisten-Import statt in die generische Inbox (Muster einvoice_intake).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('email_connections', function (Blueprint $table): void {
            $table->boolean('callreport_intake')->default(false);
        });
    }

    public function down(): void {
        Schema::table('email_connections', function (Blueprint $table): void {
            $table->dropColumn('callreport_intake');
        });
    }
};
