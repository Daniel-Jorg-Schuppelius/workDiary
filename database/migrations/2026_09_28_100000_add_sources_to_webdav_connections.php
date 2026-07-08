<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_28_100000_add_sources_to_webdav_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aktivierte Spiegel-Quellen je WebDAV-Anbindung (Feature 058, MVP-127, Rang 19):
 * welche Inhalte die Verbindung spiegelt — `document` (freigegebene DMS-Dokumente),
 * `invoice_pdf` (finalisierte Rechnungen) und/oder `protocol_pdf` (signierte
 * Protokolle). Null/leer wird als „nur document" behandelt (rückwärtskompatibel).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('webdav_connections', function (Blueprint $table): void {
            $table->json('sources')->nullable()->after('folder_map');
        });
    }

    public function down(): void {
        Schema::table('webdav_connections', function (Blueprint $table): void {
            $table->dropColumn('sources');
        });
    }
};
