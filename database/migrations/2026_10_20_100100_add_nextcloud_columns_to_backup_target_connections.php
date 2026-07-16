<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_20_100100_add_nextcloud_columns_to_backup_target_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nextcloud-Backupziel (Feature 017 Phase 32, MVP-383). Additiv wie beim
 * Dokumenteingang: Server-URL + Nutzer je Verbindung, App-Passwort in der
 * vorhandenen verschlüsselten `access_token`-Spalte. Systemweit (Plattform-
 * Admin) — bewusst ohne organization_id.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('backup_target_connections', function (Blueprint $table): void {
            $table->string('server_url', 512)->nullable()->after('external_account_label');
            $table->string('username', 190)->nullable()->after('server_url');
        });
    }

    public function down(): void {
        Schema::table('backup_target_connections', function (Blueprint $table): void {
            $table->dropColumn(['server_url', 'username']);
        });
    }
};
