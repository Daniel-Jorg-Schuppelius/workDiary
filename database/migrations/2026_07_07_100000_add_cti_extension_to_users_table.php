<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_07_100000_add_cti_extension_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CTI-Anrufer-Pop-up (Feature 056, MVP-118, Rang 9): pro Mitarbeiter
 * hinterlegbare eigene Durchwahl als Opt-in. Die Durchwahl selbst liegt
 * verschlüsselt (at-rest, PII), der SHA-256-Hash der E164-Form dient dem
 * schnellen, indizierten Rückwärts-Lookup „angerufene Nummer → opted-in User"
 * (verschlüsselte Werte sind nicht SQL-suchbar — Muster wie
 * cti_connections.webhook_token_hash). Ohne Durchwahl kein Pop-up (Datenschutz).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('cti_extension')->nullable()->after('preferences');
            $table->string('cti_extension_hash', 64)->nullable()->after('cti_extension');
            $table->index('cti_extension_hash', 'users_cti_ext_hash_idx');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_cti_ext_hash_idx');
            $table->dropColumn(['cti_extension', 'cti_extension_hash']);
        });
    }
};
