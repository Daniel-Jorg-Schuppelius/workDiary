<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_20_100000_add_nextcloud_columns_to_cloud_document_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nextcloud-Dokumenteingang (Feature 080, MVP-382). Additiv: die bisherigen
 * Provider (Dropbox/Graph/Google) sind OAuth-basiert; Nextcloud bindet je
 * Verbindung mit Server-URL + Nutzer an (App-Passwort liegt in der
 * vorhandenen, verschlüsselten `access_token`-Spalte).
 *
 * `checkpoint` trägt bei Nextcloud den Zustand des budgetierten rekursiven
 * Scans (Ordner-Queue + gesehene fileids) statt eines kurzen Server-Cursors —
 * dafür von string(2048) auf mediumText erweitert (die kurzen Cursor der
 * anderen Provider passen unverändert hinein).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->string('server_url', 512)->nullable()->after('external_account_label');
            $table->string('username', 190)->nullable()->after('server_url');
            $table->mediumText('checkpoint')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->dropColumn(['server_url', 'username']);
            $table->string('checkpoint', 2048)->nullable()->change();
        });
    }
};
