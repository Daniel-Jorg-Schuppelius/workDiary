<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_160000_add_signature_to_access_medium_handovers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unterschrifts-Verweis am Übergabevorgang (Feature 092, Folgepunkt):
 * dasselbe schlichte Muster wie KeyHandover.signature_token — ein Verweis
 * auf eine erfasste Signatur, keine eigene Pad-Strecke.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('access_medium_handovers', function (Blueprint $table): void {
            $table->string('signature_token', 64)->nullable()->after('condition');
        });
    }

    public function down(): void {
        Schema::table('access_medium_handovers', function (Blueprint $table): void {
            $table->dropColumn('signature_token');
        });
    }
};
