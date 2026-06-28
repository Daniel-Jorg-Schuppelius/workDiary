<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_27_100000_add_remote_fields_to_supplier_catalog_sources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remote-Katalogquellen (Feature 050, MVP-091, „Später"): HTTP(S)/FTP-Abruf mit
 * verschlüsselt gespeicherten Zugangsdaten sowie ein persistiertes CSV-Mapping
 * (damit ein automatischer Abruf ohne erneutes manuelles Mapping importieren
 * kann). `source_type` (upload/http/ftp/sftp) existiert bereits.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->string('remote_url', 1024)->nullable()->after('source_type');
            $table->string('remote_host', 191)->nullable()->after('remote_url');
            $table->unsignedInteger('remote_port')->nullable()->after('remote_host');
            $table->string('remote_path', 1024)->nullable()->after('remote_port');
            $table->string('remote_username', 191)->nullable()->after('remote_path');
            $table->text('remote_password')->nullable()->after('remote_username'); // verschlüsselt (Model-Cast)
            $table->json('mapping')->nullable()->after('remote_password');          // persistiertes CSV-Mapping
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn(['remote_url', 'remote_host', 'remote_port', 'remote_path', 'remote_username', 'remote_password', 'mapping']);
        });
    }
};
