<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110400_add_sftp_host_fingerprint_to_time_export_delivery_configs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host-Key-Bindung für den SFTP-Lohnexport (Sicherheitsscan 2026-08-23, S-22).
 *
 * `SftpConnectionProvider` überspringt die Host-Key-Prüfung, solange kein
 * Fingerprint gesetzt ist — die Anwendung meldete sich also bei **jedem**
 * Server an, der die Ziel-IP beantwortet, und übergab Benutzername, Passwort
 * und die Lohnexport-Datei. Ohne bekannten Host-Key gibt es keine
 * Gegenprobe; der Fingerprint ist die einzige Bindung an den richtigen
 * Empfänger.
 *
 * Nullable, weil bestehende Konfigurationen ihn noch nicht haben. Der Upload
 * verweigert dann den Dienst mit einer Meldung, die sagt, was zu tun ist —
 * lieber ein sichtbarer Abbruch als eine stille Übergabe an den Falschen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_export_delivery_configs', function (Blueprint $table): void {
            $table->string('sftp_host_fingerprint', 190)->nullable()->after('sftp_root');
        });

        // Gleiche Lücke im Katalogabruf (Lieferanten-Kataloge über SFTP).
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->string('remote_host_fingerprint', 190)->nullable()->after('remote_password');
        });
    }

    public function down(): void {
        Schema::table('time_export_delivery_configs', function (Blueprint $table): void {
            $table->dropColumn('sftp_host_fingerprint');
        });

        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn('remote_host_fingerprint');
        });
    }
};
